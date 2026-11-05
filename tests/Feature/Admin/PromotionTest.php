<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\Classes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    private AcademicYear $currentYear;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currentYear = AcademicYear::create([
            'name' => '2025/2026',
            'start_date' => '2025-07-14',
            'end_date' => '2026-06-30',
            'is_active' => true,
        ]);
    }

    public function test_guests_are_redirected_and_non_admins_are_forbidden(): void
    {
        $this->get(route('admin.promotions.index'))->assertRedirect(route('login'));

        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);

        $this->actingAs($teacher)->get(route('admin.promotions.index'))->assertForbidden();
        $this->actingAs($teacher)->post(route('admin.promotions.store'))->assertForbidden();
    }

    public function test_index_plans_every_class_of_the_active_year_at_once(): void
    {
        $tenth = $this->makeLevelClass('X TKJ 1', 'X', $this->currentYear);
        $twelfth = $this->makeLevelClass('XII TKJ 1', 'XII', $this->currentYear);
        $this->makeLevelClass('XI TKJ 1', 'XI', $this->currentYear);
        $this->makeStudent($tenth, 'Andi Saputra', '24001');
        $this->makeStudent($twelfth, 'Dedi', '22001');

        $response = $this->actingAs($this->admin())->get(route('admin.promotions.index'));

        $response->assertOk();
        $response->assertSee('2026/2027');
        $response->assertSee('Andi Saputra');
        $response->assertSee('value="XI TKJ 1"', false);
        $response->assertViewHas('plans', function ($plans) {
            return $plans->count() === 2
                && $plans[0]['defaultAction'] === 'naik'
                && $plans[0]['targetIsNew']
                && $plans[1]['defaultAction'] === 'lulus';
        });
    }

    public function test_index_handles_a_school_with_no_academic_year(): void
    {
        AcademicYear::query()->delete();

        $this->actingAs($this->admin())->get(route('admin.promotions.index'))
            ->assertOk()
            ->assertSee('Belum ada tahun ajaran.');
    }

    public function test_whole_school_is_processed_in_one_step(): void
    {
        $tenth = $this->makeLevelClass('X TKJ 1', 'X', $this->currentYear);
        $eleventh = $this->makeLevelClass('XI TKJ 1', 'XI', $this->currentYear);
        $twelfth = $this->makeLevelClass('XII TKJ 1', 'XII', $this->currentYear);

        $promoted = $this->makeStudent($tenth, 'Andi', '24001');
        $keptBack = $this->makeStudent($tenth, 'Bunga', '24002');
        $alsoPromoted = $this->makeStudent($eleventh, 'Eka', '23001');
        $graduated = $this->makeStudent($twelfth, 'Citra', '22001');

        $response = $this->actingAs($this->admin())->post(route('admin.promotions.store'), [
            'source_academic_year_id' => $this->currentYear->id,
            'actions' => [$keptBack->studentProfile->id => 'tinggal'],
        ]);

        $response->assertRedirect(route('admin.promotions.index', ['source_academic_year_id' => $this->currentYear->id]));
        $response->assertSessionHasNoErrors();

        $targetYear = AcademicYear::where('name', '2026/2027')->firstOrFail();

        $this->assertSame('2026-07-14', $targetYear->start_date->toDateString());
        $this->assertTrue($targetYear->is_active);
        $this->assertFalse($this->currentYear->fresh()->is_active);
        $this->assertSame(3, $targetYear->classes()->count());

        $this->assertSame('XI TKJ 1', $promoted->studentProfile->fresh()->class->name);
        $this->assertSame($targetYear->id, $promoted->studentProfile->fresh()->class->academic_year_id);
        $this->assertSame('XII TKJ 1', $alsoPromoted->studentProfile->fresh()->class->name);
        $this->assertSame('X TKJ 1', $keptBack->studentProfile->fresh()->class->name);
        $this->assertSame($targetYear->id, $keptBack->studentProfile->fresh()->class->academic_year_id);

        $this->assertNull($graduated->studentProfile->fresh()->class_id);
        $this->assertFalse($graduated->fresh()->is_active);
        $this->assertTrue($promoted->fresh()->is_active);

        $this->assertDatabaseHas('class_memberships', ['class_id' => $tenth->id, 'student_id' => $promoted->id]);
        $this->assertDatabaseHas('class_memberships', ['class_id' => $twelfth->id, 'student_id' => $graduated->id]);
    }

    public function test_teachers_and_schedules_follow_the_class_with_the_same_name(): void
    {
        $eleventh = $this->makeLevelClass('XI TKJ 1', 'XI', $this->currentYear);
        $subject = $this->makeSubject('Jaringan', 'JRG');
        $teacher = $this->makeTeacher();
        $this->assignTeacher($teacher, $eleventh, $subject);
        $this->makeSchedule($subject, $eleventh);
        $this->makeStudent($eleventh, 'Eka', '23001');

        $this->actingAs($this->admin())->post(route('admin.promotions.store'), [
            'source_academic_year_id' => $this->currentYear->id,
        ])->assertSessionHasNoErrors();

        $targetYear = AcademicYear::where('name', '2026/2027')->firstOrFail();
        $copy = $targetYear->classes()->where('name', 'XI TKJ 1')->firstOrFail();

        $this->assertDatabaseHas('teaching_assignments', [
            'academic_year_id' => $targetYear->id,
            'class_id' => $copy->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
        ]);
        $this->assertSame(1, $copy->schedules()->count());
        $this->assertSame(1, $eleventh->teachingAssignments()->count());
    }

    public function test_new_year_starts_with_a_clean_board_while_old_data_is_kept(): void
    {
        $source = $this->makeLevelClass('X TKJ 1', 'X', $this->currentYear);
        $subject = $this->makeSubject('Jaringan', 'JRG');
        $teacher = $this->makeTeacher();
        $this->assignTeacher($teacher, $source, $subject);
        $this->makeStudent($source, 'Andi', '24001');
        $oldAssignment = $this->makeAssignment($subject, $source, $teacher, ['title' => 'Tugas Tahun Lalu']);
        $oldAnnouncement = $this->makeAnnouncement(['title' => 'Libur Semester']);
        $scheduledAnnouncement = $this->makeAnnouncement(['title' => 'Selamat Datang', 'published_at' => now()->addWeek()]);

        $this->actingAs($this->admin())->post(route('admin.promotions.store'), [
            'source_academic_year_id' => $this->currentYear->id,
        ])->assertSessionHasNoErrors();

        $this->assertFalse($oldAnnouncement->fresh()->is_published);
        $this->assertTrue($scheduledAnnouncement->fresh()->is_published);
        $this->assertModelExists($oldAssignment);

        $newClass = AcademicYear::where('is_active', true)->firstOrFail()->classes()->where('name', 'X TKJ 1')->firstOrFail();

        $this->actingAs($teacher)->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertViewHas('assignmentsCreated', 0)
            ->assertViewHas('assignments', fn ($assignments) => $assignments->pluck('class_id')->all() === [$newClass->id]);
    }

    public function test_existing_target_year_and_its_setup_are_reused(): void
    {
        $targetYear = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-13', 'end_date' => '2027-06-30', 'is_active' => false]);
        $existingTarget = $this->makeLevelClass('XI TKJ 1', 'XI', $targetYear);
        $source = $this->makeLevelClass('X TKJ 1', 'X', $this->currentYear);
        $student = $this->makeStudent($source, 'Andi', '24001');

        $this->actingAs($this->admin())->post(route('admin.promotions.store'), [
            'source_academic_year_id' => $this->currentYear->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, AcademicYear::where('name', '2026/2027')->count());
        $this->assertSame($existingTarget->id, $student->studentProfile->fresh()->class_id);
    }

    public function test_summary_names_promoted_classes_that_still_need_teachers(): void
    {
        $source = $this->makeLevelClass('X TKJ 1', 'X', $this->currentYear);
        $this->makeStudent($source, 'Andi', '24001');

        $this->actingAs($this->admin())->post(route('admin.promotions.store'), [
            'source_academic_year_id' => $this->currentYear->id,
        ])->assertSessionHas('success', 'Kenaikan kelas ke 2026/2027 selesai: 1 siswa naik kelas. Tahun ajaran 2026/2027 sekarang aktif. Kelas yang belum punya guru: XI TKJ 1.');
    }

    public function test_a_custom_target_name_is_used(): void
    {
        $source = $this->makeLevelClass('X TKJ 1', 'X', $this->currentYear);
        $student = $this->makeStudent($source, 'Andi', '24001');

        $this->actingAs($this->admin())->post(route('admin.promotions.store'), [
            'source_academic_year_id' => $this->currentYear->id,
            'targets' => [$source->id => 'XI TKJ A'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('XI TKJ A', $student->studentProfile->fresh()->class->name);
    }

    public function test_class_with_an_unguessable_next_name_needs_a_target(): void
    {
        $source = $this->makeLevelClass('Kelas Mawar', 'X', $this->currentYear);
        $student = $this->makeStudent($source, 'Andi', '24001');

        $this->actingAs($this->admin())->post(route('admin.promotions.store'), [
            'source_academic_year_id' => $this->currentYear->id,
        ])->assertSessionHasErrors("targets.{$source->id}");

        $this->assertSame($source->id, $student->studentProfile->fresh()->class_id);
        $this->assertDatabaseMissing('academic_years', ['name' => '2026/2027']);
    }

    public function test_processing_a_past_year_leaves_the_active_year_alone(): void
    {
        $this->currentYear->update(['is_active' => false]);
        $active = AcademicYear::create(['name' => '2030/2031', 'start_date' => '2030-07-14', 'end_date' => '2031-06-30', 'is_active' => true]);
        $source = $this->makeLevelClass('X TKJ 1', 'X', $this->currentYear);
        $this->makeStudent($source, 'Andi', '24001');

        $this->actingAs($this->admin())->post(route('admin.promotions.store'), [
            'source_academic_year_id' => $this->currentYear->id,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($active->fresh()->is_active);
        $this->assertFalse(AcademicYear::where('name', '2026/2027')->firstOrFail()->is_active);
    }

    public function test_year_with_no_students_is_rejected_so_a_repeat_submit_is_harmless(): void
    {
        $source = $this->makeLevelClass('X TKJ 1', 'X', $this->currentYear);
        $student = $this->makeStudent($source, 'Andi', '24001');
        $admin = $this->admin();
        $payload = ['source_academic_year_id' => $this->currentYear->id];

        $this->actingAs($admin)->post(route('admin.promotions.store'), $payload)->assertSessionHasNoErrors();
        $classAfterFirstRun = $student->studentProfile->fresh()->class_id;

        $this->actingAs($admin)->post(route('admin.promotions.store'), $payload)->assertSessionHasErrors('source_academic_year_id');

        $this->assertSame($classAfterFirstRun, $student->studentProfile->fresh()->class_id);
    }

    private function makeLevelClass(string $name, string $level, AcademicYear $academicYear): Classes
    {
        return Classes::create([
            'academic_year_id' => $academicYear->id,
            'name' => $name,
            'code' => 'CLS-'.substr(md5($name.$academicYear->id), 0, 8),
            'level' => $level,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
