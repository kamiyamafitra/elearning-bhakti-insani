<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Announcement;
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

        $sourceYear = $academicYears->firstWhere('id', $request->integer('source_academic_year_id'))
            ?? $academicYears->firstWhere('is_active', true)
            ?? $academicYears->first();

        if ($sourceYear === null) {
            return view('admin.promotions.index', compact('academicYears', 'sourceYear'));
        }

        $targetYearName = $this->nextYearName($sourceYear);
        $targetYear = AcademicYear::where('name', $targetYearName)->first();
        $targetClassNames = $targetYear
            ? $targetYear->classes()->pluck('name')->map(fn (string $name) => Str::lower($name))
            : collect();

        $plans = $this->classesWithStudents($sourceYear)->map(function (Classes $class) use ($targetClassNames): array {
            $targetName = $this->nextLevelName($class);

            return [
                'class' => $class,
                'students' => $class->students->sortBy('user.name')->values(),
                'isFinal' => $this->isFinalLevel($class),
                'defaultAction' => $this->defaultAction($class),
                'targetName' => $targetName,
                'targetIsNew' => $targetName !== null && ! $targetClassNames->contains(Str::lower($targetName)),
            ];
        });

        return view('admin.promotions.index', compact('academicYears', 'sourceYear', 'targetYearName', 'targetYear', 'plans'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'targets' => ['nullable', 'array'],
            'targets.*' => ['nullable', 'string', 'max:50'],
            'actions' => ['nullable', 'array'],
            'actions.*' => [Rule::in([self::ACTION_PROMOTE, self::ACTION_REPEAT, self::ACTION_GRADUATE])],
        ]);

        $sourceYear = AcademicYear::findOrFail($validated['source_academic_year_id']);
        $classes = $this->classesWithStudents($sourceYear);

        if ($classes->isEmpty()) {
            throw ValidationException::withMessages([
                'source_academic_year_id' => "Tidak ada siswa untuk diproses di tahun ajaran {$sourceYear->name}.",
            ]);
        }

        $actions = collect($validated['actions'] ?? []);
        $targetNames = $this->resolveTargetNames($classes, $actions, $validated['targets'] ?? []);
        $counts = collect();

        $targetYear = ActivityLog::withoutLogging(fn () => DB::transaction(function () use ($sourceYear, $classes, $actions, $targetNames, $counts): AcademicYear {
            $targetYear = AcademicYear::firstOrCreate(['name' => $this->nextYearName($sourceYear)], [
                'start_date' => $sourceYear->start_date->copy()->addYear(),
                'end_date' => $sourceYear->end_date->copy()->addYear(),
                'is_active' => false,
            ]);

            foreach ($sourceYear->classes()->with(['teachingAssignments', 'schedules'])->get() as $class) {
                $this->copySetup($class, $this->findOrCreateClass($targetYear, $class->name, $class->level, $class->code));
            }

            foreach ($classes as $class) {
                foreach ($class->students as $student) {
                    $action = $actions[$student->id] ?? $this->defaultAction($class);
                    $counts[$action] = ($counts[$action] ?? 0) + 1;

                    $targetClass = match ($action) {
                        self::ACTION_PROMOTE => $this->findOrCreateClass($targetYear, $targetNames[$class->id], $this->nextLevel($class) ?? $class->level),
                        self::ACTION_REPEAT => $this->findOrCreateClass($targetYear, $class->name, $class->level, $class->code),
                        default => null,
                    };

                    $student->update(['class_id' => $targetClass?->id]);

                    if ($targetClass === null) {
                        $student->user->update(['is_active' => false]);
                    }
                }
            }

            if ($sourceYear->is_active) {
                AcademicYear::where('is_active', true)->update(['is_active' => false]);
                $targetYear->update(['is_active' => true]);

                // Start the new year with a clean board; announcements scheduled for later stay queued.
                Announcement::where('is_published', true)
                    ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))
                    ->update(['is_published' => false]);
            }

            return $targetYear;
        }));

        ActivityLog::record(ActivityLog::ACTION_BULK, "memproses kenaikan kelas {$sourceYear->name} ke {$targetYear->name}", $targetYear, ['counts' => $counts->all()]);

        return redirect()
            ->route('admin.promotions.index', ['source_academic_year_id' => $sourceYear->id])
            ->with('success', $this->summary($targetYear, $counts));
    }

    /**
     * @return Collection<int, Classes>
     */
    private function classesWithStudents(AcademicYear $academicYear): Collection
    {
        return $academicYear->classes()
            ->whereHas('students')
            ->with('students.user')
            ->orderBy('name')
            ->get();
    }

    /**
     * Pick each class's promotion target, requiring one only when a student actually moves up.
     *
     * @param  Collection<int, Classes>  $classes
     * @param  Collection<int|string, string>  $actions
     * @param  array<int|string, string|null>  $targets
     * @return array<int, string>
     */
    private function resolveTargetNames(Collection $classes, Collection $actions, array $targets): array
    {
        $names = [];

        foreach ($classes as $class) {
            $isPromoting = $class->students->contains(
                fn (StudentProfile $student) => ($actions[$student->id] ?? $this->defaultAction($class)) === self::ACTION_PROMOTE
            );

            if (! $isPromoting) {
                continue;
            }

            $name = trim((string) ($targets[$class->id] ?? '')) ?: $this->nextLevelName($class);

            if ($name === null) {
                throw ValidationException::withMessages([
                    "targets.{$class->id}" => "Isi kelas tujuan untuk siswa {$class->name} yang naik kelas.",
                ]);
            }

            $names[$class->id] = $name;
        }

        return $names;
    }

    private function findOrCreateClass(AcademicYear $academicYear, string $name, ?string $level, ?string $sourceCode = null): Classes
    {
        return Classes::where('academic_year_id', $academicYear->id)->where('name', $name)->first()
            ?? Classes::create([
                'academic_year_id' => $academicYear->id,
                'name' => $name,
                'code' => Classes::uniqueCodeFor($sourceCode ?? Str::upper(Str::slug($name)), $academicYear),
                'level' => $level,
            ]);
    }

    /**
     * Carry teachers and schedules over to the same class next year, keeping anything already set up there.
     */
    private function copySetup(Classes $source, Classes $target): void
    {
        if (! $target->teachingAssignments()->exists()) {
            foreach ($source->teachingAssignments as $assignment) {
                $target->teachingAssignments()->create([
                    'academic_year_id' => $target->academic_year_id,
                    'teacher_id' => $assignment->teacher_id,
                    'subject_id' => $assignment->subject_id,
                ]);
            }
        }

        if (! $target->schedules()->exists()) {
            foreach ($source->schedules as $schedule) {
                $target->schedules()->create($schedule->only(['subject_id', 'day_of_week', 'start_time', 'end_time']));
            }
        }
    }

    /**
     * @param  Collection<string, int>  $counts
     */
    private function summary(AcademicYear $targetYear, Collection $counts): string
    {
        $results = collect([
            self::ACTION_PROMOTE => 'naik kelas',
            self::ACTION_REPEAT => 'tinggal kelas',
            self::ACTION_GRADUATE => 'lulus (akun dinonaktifkan)',
        ])
            ->filter(fn (string $label, string $action) => $counts->has($action))
            ->map(fn (string $label, string $action) => "{$counts[$action]} siswa {$label}")
            ->implode(', ');

        $message = "Kenaikan kelas ke {$targetYear->name} selesai: {$results}.";

        if ($targetYear->is_active) {
            $message .= " Tahun ajaran {$targetYear->name} sekarang aktif.";
        }

        $classesWithoutTeachers = $targetYear->classes()
            ->whereHas('students')
            ->whereDoesntHave('teachingAssignments')
            ->orderBy('name')
            ->pluck('name');

        if ($classesWithoutTeachers->isNotEmpty()) {
            $message .= ' Kelas yang belum punya guru: '.$classesWithoutTeachers->implode(', ').'.';
        }

        return $message;
    }

    private function nextYearName(AcademicYear $academicYear): string
    {
        [$firstYear, $secondYear] = array_map('intval', explode('/', $academicYear->name));

        return ($firstYear + 1).'/'.($secondYear + 1);
    }

    private function defaultAction(Classes $class): string
    {
        return $this->isFinalLevel($class) ? self::ACTION_GRADUATE : self::ACTION_PROMOTE;
    }

    private function isFinalLevel(Classes $class): bool
    {
        return in_array(Str::lower((string) $class->level), self::FINAL_LEVELS, true);
    }

    private function nextLevel(Classes $class): ?string
    {
        return self::NEXT_LEVELS[Str::lower((string) $class->level)] ?? null;
    }

    /**
     * Guess the next class name by bumping the leading level, e.g. "X TKJ 1" becomes "XI TKJ 1".
     */
    private function nextLevelName(Classes $class): ?string
    {
        $nextLevel = $this->nextLevel($class);

        if ($nextLevel === null || ! Str::startsWith(Str::lower($class->name), Str::lower($class->level).' ')) {
            return null;
        }

        return $nextLevel.Str::substr($class->name, Str::length($class->level));
    }
}
