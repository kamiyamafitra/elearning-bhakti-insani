<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Concerns\FiltersSubjectIndex;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\LearningMaterial;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubjectController extends Controller
{
    use FiltersSubjectIndex;

    public function index(Request $request): View
    {
        $user = $request->user()->load(['studentProfile.class.academicYear']);
        $classId = $user->studentClassId();

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:name_asc,name_desc,schedule_asc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = $validated['q'] ?? null;
        $sort = $validated['sort'] ?? 'name_asc';

        $subjectsQuery = Subject::query()
            ->when(
                $classId !== null,
                fn (Builder $query) => $query->forClass($classId),
                fn (Builder $query) => $query->whereRaw('1 = 0'),
            );

        $totalSubjects = (clone $subjectsQuery)->count();

        $this->applySubjectSearch($subjectsQuery, $search);
        $this->applySubjectSorting($subjectsQuery, $sort, $classId);

        $subjects = $subjectsQuery
            ->with([
                'schedules' => fn ($query) => $query
                    ->when(
                        $classId !== null,
                        fn ($query) => $query->where('class_id', $classId),
                        fn ($query) => $query->whereRaw('1 = 0'),
                    )
                    ->ordered()
                    ->with('class:id,name'),
                'teachingAssignments' => fn ($query) => $query
                    ->when(
                        $classId !== null,
                        fn ($query) => $query->where('class_id', $classId),
                        fn ($query) => $query->whereRaw('1 = 0'),
                    )
                    ->with(['teacher:id,name', 'class:id,name']),
            ])
            ->paginate(9)
            ->withQueryString();

        return view('student.subjects.index', [
            'student' => $user->studentProfile,
            'classroom' => $user->studentProfile?->class,
            'subjects' => $subjects,
            'totalSubjects' => $totalSubjects,
            'search' => $search,
            'sort' => $sort,
        ]);
    }

    public function show(Request $request, Subject $subject): View
    {
        $user = $request->user()->load('studentProfile.class');
        $classId = $user->studentClassId();

        abort_unless(
            $classId !== null && Subject::query()->forClass($classId)->whereKey($subject->id)->exists(),
            404,
        );

        $subject->load([
            'schedules' => fn ($query) => $query->where('class_id', $classId)->orderBy('day_of_week')->orderBy('start_time'),
            'teachingAssignments' => fn ($query) => $query->where('class_id', $classId)->with('teacher:id,name'),
        ]);

        $learningMaterials = LearningMaterial::query()
            ->published()
            ->where('subject_id', $subject->id)
            ->where('class_id', $classId)
            ->with('teacher:id,name')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get();

        $lmsAssignments = Assignment::query()
            ->published()
            ->where('subject_id', $subject->id)
            ->where('class_id', $classId)
            ->with([
                'teacher:id,name',
                'submissions' => fn ($query) => $query->where('student_id', $user->id),
            ])
            ->orderBy('due_at')
            ->get();

        $tab = in_array($request->query('tab'), ['materials', 'assignments'], true)
            ? $request->query('tab')
            : 'materials';

        return view('student.subjects.show', [
            'student' => $user->studentProfile,
            'classroom' => $user->studentProfile?->class,
            'subject' => $subject,
            'learningMaterials' => $learningMaterials,
            'lmsAssignments' => $lmsAssignments,
            'tab' => $tab,
        ]);
    }
}
