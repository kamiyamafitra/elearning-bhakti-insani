<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_the_class_detail_page(): void
    {
        $class = $this->createClass('X TKJ 1', '10');

        $this->get(route('admin.classes.show', $class))->assertRedirect(route('login'));
    }

    public function test_detail_page_displays_class_information_and_its_students(): void
    {
        $class = $this->createClass('X TKJ 1', '10');

        $student = $this->createStudent($class, [
            'nis' => '24001',
            'nisn' => '0071234567',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.classes.show', $class));

        $response->assertOk();
        $response->assertSee('Detail Kelas');
        $response->assertSee('X TKJ 1');
        $response->assertSee($class->code);
        $response->assertSee('Informasi Kelas');
        $response->assertSeeInOrder(['Tingkat', '10']);
        $response->assertSee('2025/2026');
        $response->assertSee('Daftar Siswa');
        $response->assertSee('Andi Saputra');
        $response->assertSee('AS');
        $response->assertSee('24001');
        $response->assertSee('0071234567');
        $response->assertSee(route('admin.students.show', $student), false);
        $response->assertSee(route('admin.classes.index'), false);
        $response->assertSee(route('admin.classes.edit', $class), false);
    }

    public function test_class_has_a_single_edit_button_pointing_to_the_edit_route(): void
    {
        $class = $this->createClass('X TKJ 1');

        $response = $this->actingAs($this->admin())->get(route('admin.classes.show', $class));

        $response->assertOk();

        $content = $response->getContent();

        $this->assertSame(1, substr_count($content, route('admin.classes.edit', $class)));
        $response->assertSee('btn btn-primary w-100', false);
        $response->assertSee('Ubah Data Kelas');
    }

    public function test_detail_page_shows_an_empty_state_when_the_class_has_no_students(): void
    {
        $class = $this->createClass('XI TKJ 2', '11');

        $response = $this->actingAs($this->admin())->get(route('admin.classes.show', $class));

        $response->assertOk();
        $response->assertSee('Belum ada siswa yang terdaftar di kelas ini.');
        $response->assertSee('0 Siswa');
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createStudent(Classes $class, array $attributes = []): StudentProfile
    {
        $user = User::factory()->create([
            'name' => 'Andi Saputra',
            'email' => 'andi@example.com',
            'role' => 'student',
            'is_active' => true,
        ]);

        return StudentProfile::create([
            'user_id' => $user->id,
            'class_id' => $class->id,
            ...$attributes,
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
