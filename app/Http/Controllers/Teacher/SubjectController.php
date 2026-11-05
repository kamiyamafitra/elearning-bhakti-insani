<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\FiltersSubjectIndex;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Classes;
use App\Models\ClassSchedule;
use App\Models\LearningMaterial;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubjectController extends Controller
{
    use FiltersSubjectIndex;

    public function index(Request $request): View
    {
        $user = $request->user();
        $teacherId = (int) $user->id;
        $taughtClassIds = $user->taughtClassIds();

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'sort' => ['nullable', 'in:name_asc,name_desc,schedule_asc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = $validated['q'] ?? null;
        $classId = isset($validated['class_id']) ? (int) $validated['class_id'] : null;
        $sort = $validated['sort'] ?? 'name_asc';

        $subjectsQuery = Subject::query()->forTeacher($teacherId);
        $totalSubjects = (clone $subjectsQuery)->count();

        if ($classId !== null) {
            $subjectsQuery->whereHas(
                'teachingAssignments',
                fn (Builder $query) => $query
                    ->where('teacher_id', $teacherId)
                    ->where('class_id', $classId)
                    ->inActiveYear(),
            );
        }

        $this->applySubjectSearch($subjectsQuery, $search);
        $this->applySubjectSorting($subjectsQuery, $sort, $classId, $teacherId);

        $subjects = $subjectsQuery
            ->with([
                'schedules' => fn ($query) => $query
                    ->taughtBy($teacherId)
                    ->when($classId !== null, fn ($query) => $query->where('class_id', $classId))
                    ->ordered()
                    ->with('class:id,name'),
                'teachingAssignments' => fn ($query) => $query
                    ->where('teacher_id', $teacherId)
                    ->inActiveYear()
                    ->when($classId !== null, fn ($query) => $query->where('class_id', $classId))
                    ->with([
                        'class' => fn ($query) => $query->withCount('students'),
                        'academicYear:id,name',
                    ]),
            ])
            ->paginate(9)
            ->withQueryString();

        $classes = Classes::query()
            ->whereIn('id', $taughtClassIds)
            ->with('academicYear:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'academic_year_id']);

        return view('teacher.subjects.index', [
            'teacher' => $user->teacherProfile,
            'subjects' => $subjects,
            'totalSubjects' => $totalSubjects,
            'classes' => $classes,
            'classId' => $classId,
            'search' => $search,
            'sort' => $sort,
        ]);
    }

    public function show(Request $request, Subject $subject): View
    {
        $user = $request->user();
        $teacherId = (int) $user->id;

        abort_unless(
            TeachingAssignment::query()
                ->where('teacher_id', $teacherId)
                ->where('subject_id', $subject->id)
                ->inActiveYear()
                ->exists(),
            404,
        );

        $assignments = TeachingAssignment::query()
            ->where('teacher_id', $teacherId)
            ->where('subject_id', $subject->id)
            ->inActiveYear()
            ->with([
                'class' => fn ($query) => $query->with(['students.user:id,name,email', 'academicYear:id,name']),
                'academicYear:id,name',
            ])
            ->get();

        $classIds = $assignments->pluck('class_id')->unique()->values();

        $schedules = ClassSchedule::query()
            ->where('subject_id', $subject->id)
            ->whereIn('class_id', $classIds)
            ->with('class:id,name,code')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $learningMaterials = LearningMaterial::query()
            ->where('subject_id', $subject->id)
            ->where('teacher_id', $teacherId)
            ->whereIn('class_id', $classIds)
            ->with('class:id,name')
            ->orderByDesc('is_published')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get();

        $lmsAssignments = Assignment::query()
            ->where('subject_id', $subject->id)
            ->where('teacher_id', $teacherId)
            ->whereIn('class_id', $classIds)
            ->with('class:id,name')
            ->withCount([
                'submissions',
                'submissions as pending_grading_count' => fn ($query) => $query->whereNull('graded_at'),
                'submissions as graded_count' => fn ($query) => $query->whereNotNull('graded_at'),
            ])
            ->orderByDesc('created_at')
            ->get();

        $tab = in_array($request->query('tab'), ['materials', 'assignments'], true)
            ? $request->query('tab')
            : 'materials';

        return view('teacher.subjects.show', [
            'teacher' => $user->teacherProfile,
            'subject' => $subject,
            'assignments' => $assignments,
            'schedules' => $schedules,
            'learningMaterials' => $learningMaterials,
            'lmsAssignments' => $lmsAssignments,
            'tab' => $tab,
        ]);
    }
}
