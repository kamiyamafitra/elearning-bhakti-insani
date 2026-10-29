<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassSchedule;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load('teacherProfile');

        $assignments = $user->teachingAssignments()
            ->with([
                'subject:id,code,name',
                'class' => fn ($query) => $query->withCount('students'),
                'academicYear:id,name',
            ])
            ->get();

        $classIds = $assignments->pluck('class_id')->unique()->values();

        $schedules = ClassSchedule::query()
            ->with(['subject:id,code,name', 'class:id,name,code'])
            ->whereIn('class_id', $classIds)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $announcements = Announcement::query()
            ->visibleTo($user)
            ->with('creator:id,name')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $assignmentQuery = fn () => Assignment::query()->where('teacher_id', $user->id);

        $assignmentsCreated = $assignmentQuery()->count();
        $activeAssignments = $assignmentQuery()
            ->where('status', Assignment::STATUS_PUBLISHED)
            ->where('due_at', '>=', now())
            ->count();

        $submissionQuery = fn () => AssignmentSubmission::query()
            ->whereHas('assignment', fn (Builder $query) => $query->where('teacher_id', $user->id));

        $pendingGrading = $submissionQuery()->whereNull('graded_at')->count();

        $recentSubmissions = $submissionQuery()
            ->with([
                'student:id,name',
                'assignment:id,title,subject_id,class_id',
                'assignment.subject:id,name',
                'assignment.class:id,name',
            ])
            ->orderByDesc('submitted_at')
            ->limit(5)
            ->get();

        return view('dashboard.teacher', [
            'teacher' => $user->teacherProfile,
            'assignments' => $assignments,
            'totalClasses' => $classIds->count(),
            'totalSubjects' => $assignments->pluck('subject_id')->unique()->count(),
            'totalStudents' => $classIds->isEmpty() ? 0 : StudentProfile::whereIn('class_id', $classIds)->count(),
            'assignmentsCreated' => $assignmentsCreated,
            'activeAssignments' => $activeAssignments,
            'pendingGrading' => $pendingGrading,
            'recentSubmissions' => $recentSubmissions,
            'schedules' => $schedules,
            'announcements' => $announcements,
        ]);
    }
}
