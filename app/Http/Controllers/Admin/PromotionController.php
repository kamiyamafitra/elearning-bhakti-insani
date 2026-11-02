<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Classes;
use App\Models\StudentProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public const ACTION_PROMOTE = 'naik';

    public const ACTION_REPEAT = 'tinggal';

    public const ACTION_GRADUATE = 'lulus';

    /**
     * @var array<string, string>
     */
    private const NEXT_LEVELS = [
        'x' => 'XI',
        'xi' => 'XII',
        '10' => '11',
        '11' => '12',
    ];

    /**
     * @var list<string>
     */
    private const FINAL_LEVELS = ['xii', '12'];

    public function index(Request $request): View
    {
        $academicYears = AcademicYear::orderByDesc('start_date')->get(['id', 'name', 'is_active']);

        $sourceClasses = Classes::query()
            ->join('academic_years', 'academic_years.id', '=', 'classes.academic_year_id')
            ->select('classes.*')
            ->with('academicYear:id,name')
            ->withCount('students')
            ->orderByDesc('academic_years.start_date')
            ->orderBy('classes.name')
            ->get();

        $sourceClass = $request->filled('source_class_id')
            ? Classes::with('academicYear')->find($request->integer('source_class_id'))
            : null;

        $targetYear = $request->filled('target_academic_year_id')
            ? $academicYears->firstWhere('id', $request->integer('target_academic_year_id'))
            : $academicYears->first(fn (AcademicYear $year) => $year->is_active && $year->id !== (int) $sourceClass?->academic_year_id);

        $viewData = compact('academicYears', 'sourceClasses', 'sourceClass', 'targetYear');

        if ($sourceClass === null || $targetYear === null || $targetYear->id === (int) $sourceClass->academic_year_id) {
            return view('admin.promotions.index', $viewData + [
                'isSameYear' => $sourceClass !== null && $targetYear?->id === (int) $sourceClass->academic_year_id,
            ]);
        }

        $students = $sourceClass->students()
            ->join('users', 'users.id', '=', 'student_profiles.user_id')
            ->select('student_profiles.*')
            ->with('user:id,name,email')
            ->orderBy('users.name')
            ->get();

        $targetClasses = Classes::where('academic_year_id', $targetYear->id)->orderBy('name')->get(['id', 'name', 'level']);
        $isFinalLevel = in_array(Str::lower((string) $sourceClass->level), self::FINAL_LEVELS, true);

        return view('admin.promotions.index', $viewData + [
            'isSameYear' => false,
            'students' => $students,
            'targetClasses' => $targetClasses,
            'isFinalLevel' => $isFinalLevel,
            'defaultAction' => $isFinalLevel ? self::ACTION_GRADUATE : self::ACTION_PROMOTE,
            'suggestedPromoteClassId' => $this->findClassByName($targetClasses, $this->nextLevelName($sourceClass)),
            'suggestedRepeatClassId' => $this->findClassByName($targetClasses, $sourceClass->name),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_class_id' => ['required', 'integer', 'exists:classes,id'],
            'target_academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'actions' => ['required', 'array'],
            'actions.*' => [Rule::in([self::ACTION_PROMOTE, self::ACTION_REPEAT, self::ACTION_GRADUATE])],
            'promote_class_id' => ['nullable', 'integer'],
            'repeat_class_id' => ['nullable', 'integer'],
        ], [
            'actions.required' => 'Tidak ada siswa yang diproses.',
        ]);

        $sourceClass = Classes::findOrFail($validated['source_class_id']);
        $targetYearId = (int) $validated['target_academic_year_id'];
        $actions = collect($validated['actions']);

        if ($targetYearId === (int) $sourceClass->academic_year_id) {
            throw ValidationException::withMessages([
                'target_academic_year_id' => 'Tahun ajaran tujuan harus berbeda dari tahun ajaran kelas asal.',
            ]);
        }

        $promoteClass = $this->resolveTargetClass($actions, self::ACTION_PROMOTE, $validated['promote_class_id'] ?? null, $targetYearId, 'promote_class_id', 'naik kelas');
        $repeatClass = $this->resolveTargetClass($actions, self::ACTION_REPEAT, $validated['repeat_class_id'] ?? null, $targetYearId, 'repeat_class_id', 'tinggal kelas');

        $students = StudentProfile::query()
            ->where('class_id', $sourceClass->id)
            ->whereIn('id', $actions->keys())
            ->with('user')
            ->get();

        if ($students->count() !== $actions->count()) {
            throw ValidationException::withMessages([
                'actions' => 'Sebagian siswa sudah tidak berada di kelas asal. Muat ulang halaman lalu coba lagi.',
            ]);
        }

        ActivityLog::withoutLogging(fn () => DB::transaction(function () use ($students, $actions, $promoteClass, $repeatClass): void {
            foreach ($students as $student) {
                $targetClass = match ($actions[$student->id]) {
                    self::ACTION_PROMOTE => $promoteClass,
                    self::ACTION_REPEAT => $repeatClass,
                    default => null,
                };

                $student->update(['class_id' => $targetClass?->id]);

                if ($targetClass === null) {
                    $student->user->update(['is_active' => false]);
                }
            }
        }));

        ActivityLog::record(ActivityLog::ACTION_BULK, "memproses kenaikan kelas {$sourceClass->name} untuk {$students->count()} siswa", $sourceClass, ['target_academic_year_id' => $targetYearId, 'counts' => $actions->countBy()->all()]);

        $counts = $actions->countBy();
        $summary = collect([
            self::ACTION_PROMOTE => $promoteClass ? "naik ke {$promoteClass->name}" : null,
            self::ACTION_REPEAT => $repeatClass ? "tinggal kelas di {$repeatClass->name}" : null,
            self::ACTION_GRADUATE => 'lulus (akun dinonaktifkan)',
        ])
            ->filter(fn (?string $label, string $action) => $label !== null && $counts->get($action, 0) > 0)
            ->map(fn (string $label, string $action) => $counts[$action].' siswa '.$label)
            ->implode(', ');

        return redirect()
            ->route('admin.promotions.index', [
                'source_class_id' => $sourceClass->id,
                'target_academic_year_id' => $targetYearId,
            ])
            ->with('success', "Kenaikan kelas {$sourceClass->name} berhasil diproses: {$summary}.");
    }

    /**
     * Load the class chosen for an action, requiring it only when at least one student uses that action.
     *
     * @param  Collection<int|string, string>  $actions
     */
    private function resolveTargetClass(Collection $actions, string $action, ?int $classId, int $targetYearId, string $field, string $label): ?Classes
    {
        if (! $actions->contains($action)) {
            return null;
        }

        $class = $classId ? Classes::where('academic_year_id', $targetYearId)->find($classId) : null;

        if ($class === null) {
            throw ValidationException::withMessages([
                $field => "Pilih kelas tujuan di tahun ajaran baru untuk siswa yang {$label}.",
            ]);
        }

        return $class;
    }

    /**
     * Guess the next class name by bumping the leading level, e.g. "X TKJ 1" becomes "XI TKJ 1".
     */
    private function nextLevelName(Classes $class): ?string
    {
        $nextLevel = self::NEXT_LEVELS[Str::lower((string) $class->level)] ?? null;

        if ($nextLevel === null || ! Str::startsWith(Str::lower($class->name), Str::lower($class->level).' ')) {
            return null;
        }

        return $nextLevel.Str::substr($class->name, Str::length($class->level));
    }

    /**
     * @param  Collection<int, Classes>  $classes
     */
    private function findClassByName(Collection $classes, ?string $name): ?int
    {
        if ($name === null) {
            return null;
        }

        return $classes->first(fn (Classes $class) => Str::lower($class->name) === Str::lower($name))?->id;
    }
}
