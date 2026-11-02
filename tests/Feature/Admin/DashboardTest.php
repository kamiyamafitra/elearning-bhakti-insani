<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    public function test_empty_dashboard_renders_with_friendly_placeholders(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Belum ada tahun ajaran aktif.');
        $response->assertSee('Belum ada aktivitas.');
        $response->assertSee('Tidak ada tugas yang berakhir dalam 7 hari ke depan.');
        $response->assertSee('Semua pengumpulan sudah dinilai.');
        $response->assertSee('Belum ada pengumuman');
    }

    public function test_summary_cards_count_active_people_and_current_year_classes(): void
    {
        $class = $this->makeClass('X TKJ 1');
        $this->makeClass('Kelas Lama', AcademicYear::create([
            'name' => '2024/2025', 'start_date' => '2024-07-15', 'end_date' => '2025-06-30', 'is_active' => false,
        ]));
        $this->makeStudent($class, 'Andi', '24001');
        $this->makeStudent($class, 'Lulusan', '24002')->update(['is_active' => false]);
        $teacher = $this->makeTeacher();
        $subject = $this->makeSubject('Jaringan Dasar', 'JD');

        $this->makeAssignment($subject, $class, $teacher, ['due_at' => now()->addDays(3)]);
        $this->makeAssignment($subject, $class, $teacher, ['due_at' => now()->subDay(), 'title' => 'Tugas Lewat']);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertViewHas('stats', [
            'activeStudents' => 1,
            'activeTeachers' => 1,
            'classes' => 1,
            'subjects' => 1,
            'openAssignments' => 1,
            'ungradedSubmissions' => 0,
        ]);
        $response->assertSee('Kelas 2025/2026');
        $response->assertDontSee('Belum ada tahun ajaran aktif.');
    }

    public function test_activity_feed_and_today_counts_include_materials_assignments_and_submissions(): void
    {
        $class = $this->makeClass();
        $teacher = $this->makeTeacher('Budi Santoso');
        $student = $this->makeStudent($class, 'Andi Saputra');
        $subject = $this->makeSubject('Jaringan Dasar', 'JD');

        $this->makeMaterial($subject, $class, $teacher, ['title' => 'Pengenalan Jaringan']);
        $assignment = $this->makeAssignment($subject, $class, $teacher, ['title' => 'Topologi']);
        $this->makeSubmission($assignment, $student);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertViewHas('today', ['materials' => 1, 'assignments' => 1, 'submissions' => 1]);
        $response->assertSee('mengunggah materi &quot;Pengenalan Jaringan&quot;', false);
        $response->assertSee('membuat tugas &quot;Topologi&quot;', false);
        $response->assertSee('mengumpulkan tugas &quot;Topologi&quot;', false);
        $response->assertSee('Andi Saputra');
    }

    public function test_due_soon_lists_only_published_assignments_within_seven_days_with_submission_progress(): void
    {
        $class = $this->makeClass();
        $teacher = $this->makeTeacher();
        $subject = $this->makeSubject('Jaringan Dasar', 'JD');
        $student = $this->makeStudent($class, 'Andi', '24001');
        $this->makeStudent($class, 'Bunga', '24002');

        $soon = $this->makeAssignment($subject, $class, $teacher, ['title' => 'Tugas Segera', 'due_at' => now()->addDays(2)]);
        $this->makeAssignment($subject, $class, $teacher, ['title' => 'Tugas Jauh', 'due_at' => now()->addDays(20)]);
        $this->makeAssignment($subject, $class, $teacher, ['title' => 'Tugas Draf', 'due_at' => now()->addDay(), 'status' => 'draft']);
        $this->makeSubmission($soon, $student);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertViewHas('dueSoonAssignments', fn (Collection $assignments) => $assignments->pluck('title')->all() === ['Tugas Segera']);
        $response->assertSee('1 / 2');
    }

    public function test_ungraded_submissions_are_grouped_by_teacher(): void
    {
        $class = $this->makeClass();
        $busyTeacher = $this->makeTeacher('Guru Sibuk', '111');
        $calmTeacher = $this->makeTeacher('Guru Santai', '222');
        $subject = $this->makeSubject('Jaringan Dasar', 'JD');
        $first = $this->makeStudent($class, 'Andi', '24001');
        $second = $this->makeStudent($class, 'Bunga', '24002');

        $busyAssignment = $this->makeAssignment($subject, $class, $busyTeacher);
        $this->makeSubmission($busyAssignment, $first);
        $this->makeSubmission($busyAssignment, $second);
        $this->makeSubmission($this->makeAssignment($subject, $class, $calmTeacher), $first, ['score' => 90, 'graded_at' => now()]);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertViewHas('ungradedByTeacher', fn (Collection $rows) => $rows->count() === 1
            && $rows->first()['teacher']->is($busyTeacher)
            && $rows->first()['total'] === 2);
        $response->assertSee('2 belum dinilai');
        $response->assertSee(route('admin.teachers.show', $busyTeacher->teacherProfile), false);
    }

    public function test_teachers_without_materials_lists_only_assigned_teachers_missing_materials(): void
    {
        $class = $this->makeClass();
        $subject = $this->makeSubject('Jaringan Dasar', 'JD');
        $otherSubject = $this->makeSubject('Basis Data', 'BD');
        $idleTeacher = $this->makeTeacher('Guru Belum Unggah', '111');
        $activeTeacher = $this->makeTeacher('Guru Rajin', '222');
        $this->makeTeacher('Guru Tanpa Kelas', '333');

        $this->assignTeacher($idleTeacher, $class, $subject);
        $this->assignTeacher($idleTeacher, $class, $otherSubject);
        $this->assignTeacher($activeTeacher, $class, $subject);
        $this->makeMaterial($subject, $class, $activeTeacher);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertViewHas('teachersWithoutMaterials', fn (Collection $rows) => $rows->count() === 1
            && $rows->first()['teacher']->is($idleTeacher)
            && $rows->first()['subjects'] === 'Basis Data, Jaringan Dasar');
        $response->assertSee('Guru Belum Unggah');
        $response->assertDontSee('Guru Tanpa Kelas');
    }

    public function test_latest_published_announcements_are_shown(): void
    {
        $this->makeAnnouncement(['title' => 'Libur Semester']);
        $this->makeAnnouncement(['title' => 'Draf Rahasia', 'is_published' => false]);

        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertSee('Libur Semester')
            ->assertDontSee('Draf Rahasia');
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
