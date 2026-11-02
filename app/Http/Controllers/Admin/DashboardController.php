<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Classes;
use App\Models\LearningMaterial;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const DUE_SOON_DAYS = 7;

    private const LIST_LIMIT = 6;

    public function index(): View
    {
        $activeYear = AcademicYear::where('is_active', true)->first();
        $classIds = Classes::query()
            ->when($activeYear, fn ($query) => $query->where('academic_year_id', $activeYear->id))
            ->pluck('id');

        return view('admin.dashboard.index', [
            'activeYear' => $activeYear,
            'stats' => [
                'activeStudents' => StudentProfile::whereHas('user', fn ($query) => $query->where('is_active', true))->count(),
                'activeTeachers' => TeacherProfile::whereHas('user', fn ($query) => $query->where('is_active', true))->count(),
                'classes' => $classIds->count(),
                'subjects' => Subject::count(),
                'openAssignments' => Assignment::published()->where('due_at', '>', now())->count(),
                'ungradedSubmissions' => AssignmentSubmission::whereNull('score')->count(),
            ],
            'today' => [
                'materials' => LearningMaterial::where('created_at', '>=', today())->count(),
                'assignments' => Assignment::where('created_at', '>=', today())->count(),
                'submissions' => AssignmentSubmission::where('submitted_at', '>=', today())->count(),
            ],
            'recentActivities' => $this->recentActivities(),
            'dueSoonAssignments' => $this->dueSoonAssignments(),
            'ungradedByTeacher' => $this->ungradedByTeacher(),
            'teachersWithoutMaterials' => $this->teachersWithoutMaterials($activeYear, $classIds),
            'latestAnnouncements' => Announcement::where('is_published', true)
                ->latest('published_at')
                ->limit(5)
                ->get(['id', 'title', 'published_at', 'visibility']),
        ]);
    }

    /**
     * Merge the latest materials, assignments, and submissions into one feed, newest first.
     *
     * @return Collection<int, array{icon: string, color: string, actor: string, description: string, time: Carbon}>
     */
    private function recentActivities(): Collection
    {
        $materials = LearningMaterial::with(['teacher:id,name', 'subject:id,name', 'class:id,name'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (LearningMaterial $material) => [
                'icon' => 'bi-journal-arrow-up',
                'color' => 'primary',
                'actor' => $material->teacher?->name ?? '-',
                'description' => "mengunggah materi \"{$material->title}\" untuk {$material->subject?->name} {$material->class?->name}",
                'time' => $material->created_at,
            ]);

        $assignments = Assignment::with(['teacher:id,name', 'subject:id,name', 'class:id,name'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Assignment $assignment) => [
                'icon' => 'bi-clipboard-plus',
                'color' => 'warning',
                'actor' => $assignment->teacher?->name ?? '-',
                'description' => "membuat tugas \"{$assignment->title}\" untuk {$assignment->subject?->name} {$assignment->class?->name}",
                'time' => $assignment->created_at,
            ]);

        $submissions = AssignmentSubmission::with(['student:id,name', 'assignment:id,title'])
            ->latest('submitted_at')
            ->limit(8)
            ->get()
            ->map(fn (AssignmentSubmission $submission) => [
                'icon' => 'bi-send-check',
                'color' => 'success',
                'actor' => $submission->student?->name ?? '-',
                'description' => "mengumpulkan tugas \"{$submission->assignment?->title}\"",
                'time' => $submission->submitted_at,
            ]);

        return $materials->concat($assignments)->concat($submissions)
            ->filter(fn (array $activity) => $activity['time'] !== null)
            ->sortByDesc('time')
            ->take(8)
            ->values();
    }

    /**
     * @return Collection<int, Assignment>
     */
    private function dueSoonAssignments(): Collection
    {
        return Assignment::published()
            ->whereBetween('due_at', [now(), now()->addDays(self::DUE_SOON_DAYS)])
            ->with([
                'subject:id,name',
                'teacher:id,name',
                'class' => fn ($query) => $query->select('id', 'name')->withCount('students'),
            ])
            ->withCount('submissions')
            ->orderBy('due_at')
            ->limit(self::LIST_LIMIT)
            ->get();
    }

    /**
     * Count ungraded submissions per teacher, busiest first.
     *
     * @return Collection<int, array{teacher: User, total: int}>
     */
    private function ungradedByTeacher(): Collection
    {
        $totals = AssignmentSubmission::query()
            ->join('assignments', 'assignments.id', '=', 'assignment_submissions.assignment_id')
            ->whereNull('assignment_submissions.score')
            ->groupBy('assignments.teacher_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(self::LIST_LIMIT)
            ->selectRaw('assignments.teacher_id, COUNT(*) as total')
            ->pluck('total', 'teacher_id')
            ->map(fn ($total) => (int) $total);

        $teachers = User::with('teacherProfile:id,user_id')->whereIn('id', $totals->keys())->get(['id', 'name'])->keyBy('id');

        return $totals
            ->filter(fn (int $total, int $teacherId) => $teachers->has($teacherId))
            ->map(fn (int $total, int $teacherId) => ['teacher' => $teachers[$teacherId], 'total' => $total])
            ->values();
    }

    /**
     * Find teachers assigned to classes this year who have not uploaded any material for those classes.
     *
     * @param  Collection<int, int>  $classIds
     * @return Collection<int, array{teacher: User, subjects: string}>
     */
    private function teachersWithoutMaterials(?AcademicYear $activeYear, Collection $classIds): Collection
    {
        $teachingAssignments = TeachingAssignment::query()
            ->when($activeYear, fn ($query) => $query->where('academic_year_id', $activeYear->id))
            ->with(['teacher' => fn ($query) => $query->select('id', 'name', 'is_active')->with('teacherProfile:id,user_id'), 'subject:id,name'])
            ->get();

        $teacherIdsWithMaterials = LearningMaterial::whereIn('class_id', $classIds)->distinct()->pluck('teacher_id');

        return $teachingAssignments
            ->filter(fn (TeachingAssignment $assignment) => $assignment->teacher?->is_active && ! $teacherIdsWithMaterials->contains($assignment->teacher_id))
            ->groupBy('teacher_id')
            ->map(fn (Collection $assignments) => [
                'teacher' => $assignments->first()->teacher,
                'subjects' => $assignments->pluck('subject.name')->filter()->unique()->sort()->implode(', '),
            ])
            ->sortBy(fn (array $row) => $row['teacher']->name)
            ->values();
    }
}
