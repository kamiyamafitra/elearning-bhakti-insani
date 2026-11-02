<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\Classes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassCopyTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $currentYear;

    private AcademicYear $nextYear;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currentYear = AcademicYear::create(['name' => '2025/2026', 'start_date' => '2025-07-14', 'end_date' => '2026-06-30', 'is_active' => true]);
        $this->nextYear = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-13', 'end_date' => '2027-06-30', 'is_active' => false]);
    }

    public function test_non_admins_cannot_copy_classes(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);

        $this->actingAs($teacher)->get(route('admin.academic-years.copy-classes', $this->nextYear))->assertForbidden();
    }

    public function test_copy_page_previews_classes_and_marks_existing_ones(): void
    {
        $this->makeClass('X TKJ 1', 'X-TKJ-1', $this->currentYear);
        $this->makeClass('XI TKJ 1', 'XI-TKJ-1', $this->currentYear);
        $this->makeClass('X TKJ 1', 'X-TKJ-1-NEW', $this->nextYear);

        $response = $this->actingAs($this->admin())->get(route('admin.academic-years.copy-classes', $this->nextYear));

        $response->assertOk();
        $response->assertViewHas('sourceYear', fn (AcademicYear $year) => $year->is($this->currentYear));
        $response->assertSeeInOrder(['X TKJ 1', 'Sudah ada, dilewati', 'XI TKJ 1', 'Akan disalin']);
        $response->assertSee('Salin 1 Kelas');
    }

    public function test_academic_year_index_links_to_the_copy_page(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.academic-years.index'))
            ->assertSee(route('admin.academic-years.copy-classes', $this->nextYear), false);
    }

    public function test_classes_are_copied_with_year_suffixed_codes_and_existing_names_skipped(): void
    {
        $this->makeClass('X TKJ 1', 'X-TKJ-1', $this->currentYear);
        $this->makeClass('XI TKJ 1', 'XI-TKJ-1', $this->currentYear);
        $this->makeClass('XII Teknik Komputer Jaringan 1', 'XII-TEKNIK-KOMPUTER1', $this->currentYear);
        $this->makeClass('XI TKJ 1', 'ALREADY-THERE', $this->nextYear);

        $response = $this->actingAs($this->admin())->post(route('admin.academic-years.copy-classes.store', $this->nextYear), [
            'source_academic_year_id' => $this->currentYear->id,
        ]);

        $response->assertRedirect(route('admin.classes.index', ['academic_year_id' => $this->nextYear->id]));
        $response->assertSessionHas('success', '2 kelas berhasil disalin dari 2025/2026 ke 2026/2027.');

        $this->assertDatabaseHas('classes', ['academic_year_id' => $this->nextYear->id, 'name' => 'X TKJ 1', 'code' => 'X-TKJ-1-2627', 'level' => 'X']);
        $this->assertDatabaseHas('classes', ['academic_year_id' => $this->nextYear->id, 'name' => 'XII Teknik Komputer Jaringan 1', 'code' => 'XII-TEKNIK-KOMP-2627']);
        $this->assertSame(3, Classes::where('academic_year_id', $this->nextYear->id)->count());
    }

    public function test_copying_again_does_not_duplicate_classes(): void
    {
        $this->makeClass('X TKJ 1', 'X-TKJ-1', $this->currentYear);
        $admin = $this->admin();
        $route = route('admin.academic-years.copy-classes.store', $this->nextYear);

        $this->actingAs($admin)->post($route, ['source_academic_year_id' => $this->currentYear->id]);
        $this->actingAs($admin)->post($route, ['source_academic_year_id' => $this->currentYear->id])
            ->assertSessionHas('success', 'Semua kelas dari 2025/2026 sudah ada di 2026/2027.');

        $this->assertSame(1, Classes::where('academic_year_id', $this->nextYear->id)->count());
    }

    public function test_source_year_must_differ_from_the_target_year(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.academic-years.copy-classes.store', $this->nextYear), ['source_academic_year_id' => $this->nextYear->id])
            ->assertSessionHasErrors('source_academic_year_id');
    }

    private function makeClass(string $name, string $code, AcademicYear $academicYear): Classes
    {
        return Classes::create([
            'academic_year_id' => $academicYear->id,
            'name' => $name,
            'code' => $code,
            'level' => strtok($name, ' '),
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
