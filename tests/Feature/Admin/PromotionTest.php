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

    private AcademicYear $nextYear;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currentYear = $this->makeAcademicYear('2025/2026');
        $this->nextYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-13',
            'end_date' => '2027-06-30',
            'is_active' => false,
        ]);
    }

    public function test_guests_are_redirected_and_non_admins_are_forbidden(): void
    {
        $this->get(route('admin.promotions.index'))->assertRedirect(route('login'));

        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);

        $this->actingAs($teacher)->get(route('admin.promotions.index'))->assertForbidden();
        $this->actingAs($teacher)->post(route('admin.promotions.store'))->assertForbidden();
    }

    public function test_index_lists_source_classes_without_students_until_a_class_is_chosen(): void
    {
        $this->makeLevelClass('X TKJ 1', 'X', $this->currentYear);

        $response = $this->actingAs($this->admin())->get(route('admin.promotions.index'));

        $response->assertOk();
        $response->assertSee('Kenaikan Kelas');
        $response->assertSee('X TKJ 1 (0 siswa)');
        $response->assertViewMissing('students');
    }

    public function test_choosing_a_class_lists_students_and_suggests_target_classes(): void
    {
        $source = $this->makeLevelClass('X TKJ 1', 'X', $this->currentYear);
        $promoteTarget = $this->makeLevelClass('XI TKJ 1', 'XI', $this->nextYear);
        $repeatTarget = $this->makeLevelClass('X TKJ 1', 'X', $this->nextYear);
        $this->makeStudent($source, 'Andi Saputra', '24001');

        $response = $this->actingAs($this->admin())->get(route('admin.promotions.index', [
            'source_class_id' => $source->id,
            'target_academic_year_id' => $this->nextYear->id,
        ]));

        $response->assertOk();
        $response->assertSee('Andi Saputra');
        $response->assertViewHas('suggestedPromoteClassId', $promoteTarget->id);
        $response->assertViewHas('suggestedRepeatClassId', $repeatTarget->id);
        $response->assertViewHas('defaultAction', 'naik');
    }

    public function test_final_level_classes_default_to_graduation(): void
    {
        $source = $this->makeLevelClass('XII TKJ 1', 'XII', $this->currentYear);

        $this->actingAs($this->admin())->get(route('admin.promotions.index', [
            'source_class_id' => $source->id,
            'target_academic_year_id' => $this->nextYear->id,
        ]))->assertViewHas('defaultAction', 'lulus')->assertViewHas('isFinalLevel', true);
    }

    public function test_same_target_year_shows_a_warning_instead_of_students(): void
    {
        $source = $this->makeLevelClass('X TKJ 1', 'X', $this->currentYear);

        $this->actingAs($this->admin())->get(route('admin.promotions.index', [
            'source_class_id' => $source->id,
            'target_academic_year_id' => $this->currentYear->id,
        ]))->assertViewHas('isSameYear', true)->assertViewMissing('students');
    }

    public function test_students_are_promoted_kept_back_or_graduated_with_history_recorded(): void
    {
        $source = $this->makeLevelClass('X TKJ 1', 'X', $this->currentYear);
        $promoteTarget = $this->makeLevelClass('XI TKJ 1', 'XI', $this->nextYear);
        $repeatTarget = $this->makeLevelClass('X TKJ 1', 'X', $this->nextYear);

        $promoted = $this->makeStudent($source, 'Andi', '24001');
        $keptBack = $this->makeStudent($source, 'Bunga', '24002');
        $graduated = $this->makeStudent($source, 'Citra', '24003');

        $response = $this->actingAs($this->admin())->post(route('admin.promotions.store'), [
            'source_class_id' => $source->id,
            'target_academic_year_id' => $this->nextYear->id,
            'promote_class_id' => $promoteTarget->id,
            'repeat_class_id' => $repeatTarget->id,
            'actions' => [
                $promoted->studentProfile->id => 'naik',
                $keptBack->studentProfile->id => 'tinggal',
                $graduated->studentProfile->id => 'lulus',
            ],
        ]);

        $response->assertRedirect(route('admin.promotions.index', [
            'source_class_id' => $source->id,
            'target_academic_year_id' => $this->nextYear->id,
        ]));
        $response->assertSessionHas('success', 'Kenaikan kelas X TKJ 1 berhasil diproses: 1 siswa naik ke XI TKJ 1, 1 siswa tinggal kelas di X TKJ 1, 1 siswa lulus (akun dinonaktifkan).');

        $this->assertSame($promoteTarget->id, $promoted->studentProfile->fresh()->class_id);
        $this->assertSame($repeatTarget->id, $keptBack->studentProfile->fresh()->class_id);
        $this->assertNull($graduated->studentProfile->fresh()->class_id);

        $this->assertTrue($promoted->fresh()->is_active);
        $this->assertFalse($graduated->fresh()->is_active);

        foreach ([$promoted, $keptBack, $graduated] as $student) {
            $this->assertDatabaseHas('class_memberships', [
                'academic_year_id' => $this->currentYear->id,
                'class_id' => $source->id,
                'student_id' => $student->id,
            ]);
        }

        $this->assertDatabaseHas('class_memberships', ['academic_year_id' => $this->nextYear->id, 'class_id' => $promoteTarget->id, 'student_id' => $promoted->id]);
        $this->assertDatabaseHas('class_memberships', ['academic_year_id' => $this->nextYear->id, 'class_id' => $repeatTarget->id, 'student_id' => $keptBack->id]);
        $this->assertDatabaseCount('class_memberships', 5);
    }

    public function test_graduating_a_whole_class_needs_no_target_class(): void
    {
        $source = $this->makeLevelClass('XII TKJ 1', 'XII', $this->currentYear);
        $student = $this->makeStudent($source, 'Dedi', '22001');

        $this->actingAs($this->admin())->post(route('admin.promotions.store'), [
            'source_class_id' => $source->id,
            'target_academic_year_id' => $this->nextYear->id,
            'actions' => [$student->studentProfile->id => 'lulus'],
        ])->assertSessionHasNoErrors();

        $this->assertFalse($student->fresh()->is_active);
        $this->assertNull($student->studentProfile->fresh()->class_id);
    }

    public function test_promotion_requires_a_target_class_from_the_target_year(): void
    {
        $source = $this->makeLevelClass('X TKJ 1', 'X', $this->currentYear);
        $classInWrongYear = $this->makeLevelClass('XI TKJ 1', 'XI', $this->currentYear);
        $student = $this->makeStudent($source, 'Andi', '24001');
        $admin = $this->admin();

        $payload = [
            'source_class_id' => $source->id,
            'target_academic_year_id' => $this->nextYear->id,
            'actions' => [$student->studentProfile->id => 'naik'],
        ];

        $this->actingAs($admin)->post(route('admin.promotions.store'), $payload)
            ->assertSessionHasErrors('promote_class_id');

        $this->actingAs($admin)->post(route('admin.promotions.store'), $payload + ['promote_class_id' => $classInWrongYear->id])
            ->assertSessionHasErrors('promote_class_id');

        $this->assertSame($source->id, $student->studentProfile->fresh()->class_id);
        $this->assertDatabaseCount('class_memberships', 1);
    }

    public function test_target_year_must_differ_and_students_must_belong_to_the_source_class(): void
    {
        $source = $this->makeLevelClass('X TKJ 1', 'X', $this->currentYear);
        $otherClass = $this->makeLevelClass('X RPL 1', 'X', $this->currentYear);
        $target = $this->makeLevelClass('XI TKJ 1', 'XI', $this->nextYear);
        $outsider = $this->makeStudent($otherClass, 'Orang Lain', '24009');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.promotions.store'), [
            'source_class_id' => $source->id,
            'target_academic_year_id' => $this->currentYear->id,
            'actions' => [$outsider->studentProfile->id => 'lulus'],
        ])->assertSessionHasErrors('target_academic_year_id');

        $this->actingAs($admin)->post(route('admin.promotions.store'), [
            'source_class_id' => $source->id,
            'target_academic_year_id' => $this->nextYear->id,
            'promote_class_id' => $target->id,
            'actions' => [$outsider->studentProfile->id => 'naik'],
        ])->assertSessionHasErrors('actions');

        $this->assertSame($otherClass->id, $outsider->studentProfile->fresh()->class_id);
        $this->assertTrue($outsider->fresh()->is_active);
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
