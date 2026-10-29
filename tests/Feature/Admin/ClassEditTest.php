<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\Classes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_the_class_edit_page(): void
    {
        $class = $this->createClass('X TKJ 1', '10');

        $this->get(route('admin.classes.edit', $class))->assertRedirect(route('login'));
    }

    public function test_edit_page_displays_the_existing_class_data_and_update_form(): void
    {
        $class = $this->createClass('X TKJ 1', '10');

        $response = $this->actingAs($this->admin())->get(route('admin.classes.edit', $class));

        $response->assertOk();
        $response->assertSee('Ubah Data Kelas');
        $response->assertSee('Informasi Kelas');
        $response->assertSee('value="'.$class->name.'"', false);
        $response->assertSee('value="'.$class->code.'"', false);
        $response->assertSee('value="10"', false);
        $response->assertSee('value="'.$class->academic_year_id.'" selected', false);
        $response->assertSee('2025/2026');
        $response->assertSee('action="'.route('admin.classes.update', $class).'"', false);
        $response->assertSee('name="_method" value="PUT"', false);
        $response->assertSee('name="_token"', false);
        $response->assertSee('Simpan Perubahan');
        $response->assertSee('Batal');
        $response->assertSee(route('admin.classes.index'), false);
    }

    public function test_class_can_be_updated_through_the_existing_route(): void
    {
        $class = $this->createClass('X TKJ 1', '10');
        $otherClass = $this->createClass('XI TKJ 2', '11');

        $response = $this->actingAs($this->admin())->put(route('admin.classes.update', $class), [
            'academic_year_id' => $class->academic_year_id,
            'name' => 'X TKJ 1 Baru',
            'code' => 'X-TKJ-1-BARU',
            'level' => '11',
        ]);

        $response->assertRedirect(route('admin.classes.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('classes', [
            'id' => $class->id,
            'name' => 'X TKJ 1 Baru',
            'code' => 'X-TKJ-1-BARU',
            'level' => '11',
        ]);

        $this->assertDatabaseHas('classes', [
            'id' => $otherClass->id,
            'name' => 'XI TKJ 2',
            'code' => $otherClass->code,
        ]);
    }

    public function test_update_validation_errors_are_displayed_and_old_values_are_kept(): void
    {
        $class = $this->createClass('X TKJ 1', '10');
        $otherClass = $this->createClass('XI TKJ 2', '11');

        $page = $this->actingAs($this->admin())
            ->from(route('admin.classes.edit', $class))
            ->followingRedirects()
            ->put(route('admin.classes.update', $class), [
                'academic_year_id' => $class->academic_year_id,
                'name' => 'Nama Tidak Tersimpan',
                'code' => $otherClass->code,
                'level' => '10',
            ]);

        $page->assertOk();
        $page->assertSee('Nama Tidak Tersimpan');
        $page->assertSee('is-invalid', false);
        $page->assertSee('invalid-feedback', false);

        $this->assertDatabaseHas('classes', [
            'id' => $class->id,
            'name' => 'X TKJ 1',
            'code' => $class->code,
        ]);
    }

    private function createClass(string $name, ?string $level = null): Classes
    {
        $academicYear = AcademicYear::firstOrCreate(
            ['name' => '2025/2026'],
            [
                'start_date' => '2025-07-14',
                'end_date' => '2026-06-30',
                'is_active' => true,
            ],
        );

        return Classes::create([
            'academic_year_id' => $academicYear->id,
            'name' => $name,
            'code' => 'CLS-'.substr(md5($name), 0, 6),
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
