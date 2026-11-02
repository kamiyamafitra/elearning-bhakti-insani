<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class AcademicYearControllerTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    public function test_guests_are_redirected_and_non_admins_are_forbidden(): void
    {
        $this->get(route('admin.academic-years.index'))->assertRedirect(route('login'));

        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);

        $this->actingAs($teacher)->get(route('admin.academic-years.index'))->assertForbidden();
    }

    public function test_index_lists_academic_years_with_status_and_class_counts(): void
    {
        $activeYear = $this->makeAcademicYear('2025/2026');
        $this->makeClass('X TKJ 1', $activeYear);
        $this->createYear('2024/2025', isActive: false);

        $response = $this->actingAs($this->admin())->get(route('admin.academic-years.index'));

        $response->assertOk();
        $response->assertViewHas('totalAcademicYears', 2);
        $response->assertViewHas('activeAcademicYear', fn (?AcademicYear $year) => $year?->is($activeYear));
        $response->assertSeeInOrder(['2025/2026', 'Aktif', '1 Kelas', '2024/2025', 'Nonaktif', '0 Kelas']);
        $response->assertSee(route('admin.academic-years.activate', AcademicYear::where('name', '2024/2025')->first()), false);
    }

    public function test_index_warns_when_no_academic_year_is_active(): void
    {
        $this->createYear('2024/2025', isActive: false);

        $this->actingAs($this->admin())
            ->get(route('admin.academic-years.index'))
            ->assertSee('Belum ada tahun ajaran aktif.');
    }

    public function test_storing_an_active_year_deactivates_the_previous_active_year(): void
    {
        $previousYear = $this->makeAcademicYear('2025/2026');

        $response = $this->actingAs($this->admin())->post(route('admin.academic-years.store'), [
            'name' => '2026/2027',
            'start_date' => '2026-07-13',
            'end_date' => '2027-06-30',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.academic-years.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('academic_years', ['name' => '2026/2027', 'is_active' => true]);
        $this->assertFalse($previousYear->fresh()->is_active);
    }

    public function test_storing_without_the_active_checkbox_keeps_the_current_active_year(): void
    {
        $currentYear = $this->makeAcademicYear('2025/2026');

        $this->actingAs($this->admin())->post(route('admin.academic-years.store'), [
            'name' => '2026/2027',
            'start_date' => '2026-07-13',
            'end_date' => '2027-06-30',
        ]);

        $this->assertDatabaseHas('academic_years', ['name' => '2026/2027', 'is_active' => false]);
        $this->assertTrue($currentYear->fresh()->is_active);
    }

    public function test_store_rejects_invalid_names_duplicates_and_reversed_dates(): void
    {
        $this->makeAcademicYear('2025/2026');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.academic-years.store'), [
            'name' => '2025-2026',
            'start_date' => '2025-07-14',
            'end_date' => '2026-06-30',
        ])->assertSessionHasErrors('name');

        $this->actingAs($admin)->post(route('admin.academic-years.store'), [
            'name' => '2026/2028',
            'start_date' => '2026-07-13',
            'end_date' => '2027-06-30',
        ])->assertSessionHasErrors('name');

        $this->actingAs($admin)->post(route('admin.academic-years.store'), [
            'name' => '2025/2026',
            'start_date' => '2025-07-14',
            'end_date' => '2026-06-30',
        ])->assertSessionHasErrors('name');

        $this->actingAs($admin)->post(route('admin.academic-years.store'), [
            'name' => '2026/2027',
            'start_date' => '2027-06-30',
            'end_date' => '2026-07-13',
        ])->assertSessionHasErrors('end_date');

        $this->assertDatabaseCount('academic_years', 1);
    }

    public function test_edit_page_shows_the_existing_values(): void
    {
        $year = $this->makeAcademicYear('2025/2026');

        $response = $this->actingAs($this->admin())->get(route('admin.academic-years.edit', $year));

        $response->assertOk();
        $response->assertSee('value="2025/2026"', false);
        $response->assertSee('value="2025-07-14"', false);
        $response->assertSee('value="2026-06-30"', false);
        $response->assertSee('action="'.route('admin.academic-years.update', $year).'"', false);
    }

    public function test_year_can_be_updated_keeping_its_own_name(): void
    {
        $year = $this->makeAcademicYear('2025/2026');

        $response = $this->actingAs($this->admin())->put(route('admin.academic-years.update', $year), [
            'name' => '2025/2026',
            'start_date' => '2025-07-21',
            'end_date' => '2026-06-20',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.academic-years.index'));
        $this->assertSame('2025-07-21', $year->fresh()->start_date->format('Y-m-d'));
        $this->assertTrue($year->fresh()->is_active);
    }

    public function test_activating_a_year_leaves_it_as_the_only_active_year(): void
    {
        $currentYear = $this->makeAcademicYear('2025/2026');
        $nextYear = $this->createYear('2026/2027', isActive: false);

        $response = $this->actingAs($this->admin())->patch(route('admin.academic-years.activate', $nextYear));

        $response->assertRedirect(route('admin.academic-years.index'));
        $this->assertTrue($nextYear->fresh()->is_active);
        $this->assertFalse($currentYear->fresh()->is_active);
        $this->assertSame(1, AcademicYear::where('is_active', true)->count());
    }

    public function test_unused_inactive_year_can_be_deleted(): void
    {
        $year = $this->createYear('2024/2025', isActive: false);

        $this->actingAs($this->admin())
            ->delete(route('admin.academic-years.destroy', $year))
            ->assertRedirect(route('admin.academic-years.index'));

        $this->assertModelMissing($year);
    }

    public function test_active_year_or_year_with_classes_cannot_be_deleted(): void
    {
        $activeYear = $this->makeAcademicYear('2025/2026');
        $yearWithClasses = $this->createYear('2024/2025', isActive: false);
        $this->makeClass('XII TKJ 1', $yearWithClasses);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('admin.academic-years.destroy', $activeYear))
            ->assertSessionHas('error');

        $this->actingAs($admin)
            ->delete(route('admin.academic-years.destroy', $yearWithClasses))
            ->assertSessionHas('error');

        $this->assertModelExists($activeYear);
        $this->assertModelExists($yearWithClasses);
    }

    private function createYear(string $name, bool $isActive): AcademicYear
    {
        $startYear = (int) substr($name, 0, 4);

        return AcademicYear::create([
            'name' => $name,
            'start_date' => "{$startYear}-07-14",
            'end_date' => ($startYear + 1).'-06-30',
            'is_active' => $isActive,
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
