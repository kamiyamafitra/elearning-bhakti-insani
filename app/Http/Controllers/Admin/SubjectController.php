<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Classes;
use App\Models\ClassSchedule;
use App\Models\Discussion;
use App\Models\LearningMaterial;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'sort' => ['nullable', 'in:name_asc,name_desc,class_asc,schedule_asc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = $validated['q'] ?? null;
        $classId = isset($validated['class_id']) ? (int) $validated['class_id'] : null;
        $sort = $validated['sort'] ?? 'name_asc';

        $subjects = Subject::query()
            ->with([
                'schedules' => fn ($query) => $query->with('class:id,name')->ordered(),
                'teachingAssignments' => fn ($query) => $query->with([
                    'teacher:id,name',
                    'class:id,name',
                ])->orderBy('class_id'),
            ])
            ->withCount('teachingAssignments')
            ->when($search, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($classId, function (Builder $query, int $classId) {
                $query->where(function (Builder $query) use ($classId) {
                    $query->whereHas('schedules', fn (Builder $query) => $query->where('class_id', $classId))
                        ->orWhereHas('teachingAssignments', fn (Builder $query) => $query->where('class_id', $classId));
                });
            });

        $this->applySorting($subjects, $sort);

        $subjects = $subjects->paginate(10)->withQueryString();

        $classes = $this->classOptions();

        $totalSubjects = Subject::count();

        return view('admin.subjects.index', compact(
            'subjects',
            'search',
            'totalSubjects',
            'classes',
            'classId',
            'sort',
        ));
    }

    public function create(): View
    {
        $classes = $this->classOptions();
        $teachers = $this->teacherOptions();

        return view('admin.subjects.create', compact('classes', 'teachers'));
    }

    public function store(Request $request): RedirectResponse
    {
        [$validated, $schedules] = $this->validateSubject($request);

        DB::transaction(function () use ($validated, $schedules): void {
            $subject = Subject::create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            $this->syncSchedules($subject, $schedules);
            $this->syncTeachingAssignments($subject, $schedules, []);
        });

        return redirect()->route('admin.subjects.index')->with('success', 'Mata pelajaran berhasil ditambahkan.');
    }

    public function show(Subject $subject): View
    {
        $subject->load([
            'teachingAssignments.teacher',
            'teachingAssignments.class',
            'teachingAssignments.academicYear',
            'schedules.class',
        ]);

        return view('admin.subjects.show', compact('subject'));
    }

    public function edit(Subject $subject): View
    {
        $subject->load([
            'schedules' => fn ($query) => $query->ordered(),
            'teachingAssignments',
        ]);

        $classes = $this->classOptions();
        $teachers = $this->teacherOptions();

        return view('admin.subjects.edit', compact('subject', 'classes', 'teachers'));
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        [$validated, $schedules] = $this->validateSubject($request, $subject);

        $previousClassIds = $subject->schedules()->pluck('class_id')->unique()->all();

        DB::transaction(function () use ($subject, $validated, $schedules, $previousClassIds): void {
            $subject->update([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            $this->syncSchedules($subject, $schedules);
            $this->syncTeachingAssignments($subject, $schedules, $previousClassIds);
        });

        return redirect()->route('admin.subjects.index')->with('success', 'Mata pelajaran berhasil diperbarui.');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        $hasCourseData = Assignment::where('subject_id', $subject->id)->exists()
            || LearningMaterial::where('subject_id', $subject->id)->exists()
            || Quiz::where('subject_id', $subject->id)->exists()
            || Discussion::where('subject_id', $subject->id)->exists();

        if ($hasCourseData) {
            return back()->with('error', 'Mata pelajaran tidak dapat dihapus karena masih memiliki materi, tugas, kuis, atau diskusi.');
        }

        DB::transaction(function () use ($subject): void {
            $subject->teachingAssignments()->delete();
            $subject->delete();
        });

        return redirect()->route('admin.subjects.index')->with('success', 'Mata pelajaran berhasil dihapus.');
    }

    /**
     * @return Collection<int, Classes>
     */
    private function classOptions(): Collection
    {
        return Classes::with('academicYear:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'academic_year_id']);
    }

    /**
     * @return Collection<int, User>
     */
    private function teacherOptions(): Collection
    {
        return User::query()
            ->where('role', 'teacher')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Validate the subject payload together with its schedule rows.
     *
     * @return array{0: array<string, mixed>, 1: array<int, array{id: ?int, class_id: mixed, day_of_week: mixed, start_time: mixed, end_time: mixed, teacher_id: ?int}>}
     */
    private function validateSubject(Request $request, ?Subject $subject = null): array
    {
        $existingScheduleIds = $subject?->schedules()->pluck('id')->all() ?? [];

        $schedules = collect($this->cleanScheduleInput($request))
            ->map(function (array $schedule) use ($existingScheduleIds): array {
                $schedule['id'] = in_array($schedule['id'], $existingScheduleIds, true) ? $schedule['id'] : null;

                return $schedule;
            })
            ->values()
            ->all();

        $request->merge(['schedules' => $schedules]);

        $uniqueCode = Rule::unique('subjects', 'code');

        if ($subject !== null) {
            $uniqueCode->ignore($subject->id);
        }

        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:20', $uniqueCode],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'schedules' => ['array'],
            'schedules.*.id' => ['nullable', 'integer'],
            'schedules.*.class_id' => ['required', 'integer', 'exists:classes,id'],
            'schedules.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i'],
            'schedules.*.teacher_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'teacher')),
            ],
        ]);

        $validator->after(function ($validator) use ($schedules): void {
            $submitted = [];
            $teachersByClass = [];

            foreach ($schedules as $index => $schedule) {
                $classId = $schedule['class_id'];
                $dayOfWeek = $schedule['day_of_week'];
                $startTime = $schedule['start_time'];
                $endTime = $schedule['end_time'];
                $teacherId = $schedule['teacher_id'];

                if (! is_numeric($classId) || ! is_numeric($dayOfWeek) || ! is_string($startTime) || ! is_string($endTime)) {
                    continue;
                }

                if ($teacherId !== null) {
                    if (array_key_exists((int) $classId, $teachersByClass) && $teachersByClass[(int) $classId] !== $teacherId) {
                        $validator->errors()->add(
                            "schedules.{$index}.teacher_id",
                            'Guru pengajar untuk kelas yang sama harus sama pada setiap sesi.',
                        );
                    }

                    $teachersByClass[(int) $classId] = $teacherId;
                }

                if (strtotime($endTime) <= strtotime($startTime)) {
                    $validator->errors()->add(
                        "schedules.{$index}.end_time",
                        'Waktu selesai harus lebih lambat dari waktu mulai.',
                    );

                    continue;
                }

                if ($this->scheduleOverlaps((int) $classId, (int) $dayOfWeek, $startTime, $endTime, $schedule['id'], $submitted)) {
                    $validator->errors()->add(
                        "schedules.{$index}.start_time",
                        'Jadwal bertabrakan dengan jadwal lain pada kelas dan hari yang sama.',
                    );
                }

                $submitted[] = [
                    'class_id' => (int) $classId,
                    'day_of_week' => (int) $dayOfWeek,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ];
            }
        });

        $validated = $validator->validate();

        return [$validated, $schedules];
    }

    /**
     * Create, update, and remove the subject's schedule rows.
     *
     * @param  array<int, array{id: ?int, class_id: mixed, day_of_week: mixed, start_time: mixed, end_time: mixed, teacher_id: ?int}>  $schedules
     */
    private function syncSchedules(Subject $subject, array $schedules): void
    {
        $keptScheduleIds = [];

        foreach ($schedules as $schedule) {
            $attributes = [
                'class_id' => $schedule['class_id'],
                'day_of_week' => $schedule['day_of_week'],
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
            ];

            if ($schedule['id'] !== null) {
                $existingSchedule = $subject->schedules()->whereKey($schedule['id'])->first();

                if ($existingSchedule !== null) {
                    $existingSchedule->update($attributes);
                    $keptScheduleIds[] = $existingSchedule->id;

                    continue;
                }
            }

            $keptScheduleIds[] = $subject->schedules()->create($attributes)->id;
        }

        $subject->schedules()->whereNotIn('id', $keptScheduleIds)->delete();
    }

    /**
     * Create, update, and remove teaching assignments based on the submitted schedule rows.
     *
     * Each class may have a single teacher for the subject, so the assignment is keyed
     * by the class (and its academic year) rather than by an individual schedule row.
     * Assignments for classes that were never part of this subject's schedule are left
     * untouched, so externally managed data is preserved.
     *
     * @param  array<int, array{id: ?int, class_id: mixed, day_of_week: mixed, start_time: mixed, end_time: mixed, teacher_id: ?int}>  $schedules
     * @param  array<int, int|string>  $previousClassIds
     */
    private function syncTeachingAssignments(Subject $subject, array $schedules, array $previousClassIds): void
    {
        $submittedClassIds = collect($schedules)
            ->pluck('class_id')
            ->filter(fn ($classId): bool => is_numeric($classId))
            ->map(fn ($classId): int => (int) $classId)
            ->unique()
            ->values();

        $teachersByClass = [];

        foreach ($schedules as $schedule) {
            if (! is_numeric($schedule['class_id']) || $schedule['teacher_id'] === null) {
                continue;
            }

            $teachersByClass[(int) $schedule['class_id']] = (int) $schedule['teacher_id'];
        }

        $managedClassIds = $submittedClassIds
            ->merge(collect($previousClassIds)->map(fn ($classId): int => (int) $classId))
            ->unique()
            ->values();

        $subject->teachingAssignments()
            ->whereIn('class_id', $managedClassIds)
            ->whereNotIn('class_id', array_keys($teachersByClass))
            ->delete();

        if ($teachersByClass === []) {
            return;
        }

        $existingAssignments = $subject->teachingAssignments()
            ->whereIn('class_id', array_keys($teachersByClass))
            ->get()
            ->keyBy('class_id');

        $academicYearsByClass = Classes::query()
            ->whereIn('id', array_keys($teachersByClass))
            ->pluck('academic_year_id', 'id');

        foreach ($teachersByClass as $classId => $teacherId) {
            $assignment = $existingAssignments->get($classId);

            if ($assignment !== null) {
                if ((int) $assignment->teacher_id !== $teacherId) {
                    $assignment->update(['teacher_id' => $teacherId]);
                }

                continue;
            }

            $subject->teachingAssignments()->create([
                'academic_year_id' => $academicYearsByClass->get($classId),
                'teacher_id' => $teacherId,
                'class_id' => $classId,
            ]);
        }
    }

    private function applySorting(Builder $query, string $sort): void
    {
        match ($sort) {
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            'class_asc' => $query->orderBy($this->firstClassNameSubquery())->orderBy('name'),
            'schedule_asc' => $query->orderBy($this->firstScheduledDaySubquery())
                ->orderBy($this->firstScheduledTimeSubquery())
                ->orderBy('name'),
            default => $query->orderBy('name')->orderBy('id'),
        };
    }

    private function firstClassNameSubquery(): Builder
    {
        return ClassSchedule::query()
            ->join('classes', 'classes.id', '=', 'class_schedules.class_id')
            ->whereColumn('class_schedules.subject_id', 'subjects.id')
            ->select('classes.name')
            ->orderBy('classes.name')
            ->limit(1);
    }

    private function firstScheduledDaySubquery(): Builder
    {
        return ClassSchedule::query()
            ->whereColumn('class_schedules.subject_id', 'subjects.id')
            ->select('class_schedules.day_of_week')
            ->orderBy('class_schedules.day_of_week')
            ->orderBy('class_schedules.start_time')
            ->limit(1);
    }

    private function firstScheduledTimeSubquery(): Builder
    {
        return ClassSchedule::query()
            ->whereColumn('class_schedules.subject_id', 'subjects.id')
            ->select('class_schedules.start_time')
            ->orderBy('class_schedules.day_of_week')
            ->orderBy('class_schedules.start_time')
            ->limit(1);
    }

    /**
     * @param  array<int, array{class_id: int, day_of_week: int, start_time: string, end_time: string}>  $submitted
     */
    private function scheduleOverlaps(
        int $classId,
        int $dayOfWeek,
        string $startTime,
        string $endTime,
        ?int $ignoreId,
        array $submitted,
    ): bool {
        $existsInDatabase = ClassSchedule::query()
            ->where('class_id', $classId)
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($existsInDatabase) {
            return true;
        }

        foreach ($submitted as $schedule) {
            if (
                $schedule['class_id'] === $classId
                && $schedule['day_of_week'] === $dayOfWeek
                && $schedule['start_time'] < $endTime
                && $schedule['end_time'] > $startTime
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{id: ?int, class_id: mixed, day_of_week: mixed, start_time: mixed, end_time: mixed, teacher_id: ?int}>
     */
    private function cleanScheduleInput(Request $request): array
    {
        $rawSchedules = $request->input('schedules', []);

        if (! is_array($rawSchedules)) {
            throw ValidationException::withMessages([
                'schedules' => 'Format jadwal tidak valid.',
            ]);
        }

        return collect($rawSchedules)
            ->filter(fn ($schedule): bool => is_array($schedule))
            ->filter(fn (array $schedule): bool => collect(['class_id', 'day_of_week', 'start_time', 'end_time', 'teacher_id', 'id'])
                ->contains(fn (string $key): bool => filled($schedule[$key] ?? null)))
            ->map(fn (array $schedule): array => [
                'id' => filled($schedule['id'] ?? null) ? (int) $schedule['id'] : null,
                'class_id' => $schedule['class_id'] ?? null,
                'day_of_week' => $schedule['day_of_week'] ?? null,
                'start_time' => $schedule['start_time'] ?? null,
                'end_time' => $schedule['end_time'] ?? null,
                'teacher_id' => filled($schedule['teacher_id'] ?? null) ? (int) $schedule['teacher_id'] : null,
            ])
            ->values()
            ->all();
    }
}
