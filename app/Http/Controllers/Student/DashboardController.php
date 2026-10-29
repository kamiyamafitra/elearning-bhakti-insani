<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load(['studentProfile.class.academicYear']);
        $classId = $user->studentClassId();

        $subjects = Subject::query()
            ->when(
                $classId !== null,
                fn (Builder $query) => $query->forClass($classId),
                fn (Builder $query) => $query->whereRaw('1 = 0'),
            )
            ->with([
                'schedules' => fn ($query) => $query->where('class_id', $classId)->orderBy('day_of_week')->orderBy('start_time'),
                'teachingAssignments' => fn ($query) => $query->where('class_id', $classId)->with('teacher:id,name'),
            ])
            ->orderBy('name')
            ->get();

        $announcements = Announcement::query()
            ->visibleTo($user)
            ->with('creator:id,name')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $availableAssignments = 0;
        $pendingAssignments = 0;
        $upcomingDeadlines = 0;
        $gradedAssignments = 0;
        $latestGrade = null;

        if ($classId !== null) {
            $subjectIds = $subjects->pluck('id');
            $assignmentQuery = fn () => Assignment::query()
                ->published()
                ->where('class_id', $classId)
                ->whereIn('subject_id', $subjectIds);

            $availableAssignments = $assignmentQuery()->count();

            $submittedAssignmentIds = AssignmentSubmission::query()
                ->where('student_id', $user->id)
                ->pluck('assignment_id');

            $pendingAssignments = $assignmentQuery()
                ->whereNotIn('id', $submittedAssignmentIds)
                ->count();

            $upcomingDeadlines = $assignmentQuery()
                ->whereBetween('due_at', [now(), now()->addDays(7)])
                ->count();

            $gradedQuery = fn () => AssignmentSubmission::query()
                ->where('student_id', $user->id)
                ->whereNotNull('graded_at')
                ->whereHas('assignment', fn (Builder $query) => $query
                    ->published()
                    ->where('class_id', $classId)
                    ->whereIn('subject_id', $subjectIds));

            $gradedAssignments = $gradedQuery()->count();
            $latestGrade = $gradedQuery()->orderByDesc('graded_at')->value('score');
        }

        return view('dashboard.student', [
            'student' => $user->studentProfile,
            'classroom' => $user->studentProfile?->class,
            'subjects' => $subjects,
            'totalSubjects' => $subjects->count(),
            'availableAssignments' => $availableAssignments,
            'pendingAssignments' => $pendingAssignments,
            'upcomingDeadlines' => $upcomingDeadlines,
            'gradedAssignments' => $gradedAssignments,
            'latestGrade' => $latestGrade,
            'announcements' => $announcements,
        ]);
    }
}
