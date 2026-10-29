<?php

namespace Tests\Feature\Admin;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_the_student_detail_page(): void
    {
        $student = $this->createStudent();

        $this->get(route('admin.students.show', $student))->assertRedirect(route('login'));
    }

    public function test_detail_page_displays_the_student_information(): void
    {
        $student = $this->createStudent([
            'nisn' => '0071234567',
            'nis' => '24001',
            'parent_name' => 'Bapak Santoso',
            'parent_phone' => '089876543210',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.students.show', $student));

        $response->assertOk();
        $response->assertSee('Detail Siswa');
        $response->assertSee('Andi Saputra');
        $response->assertSee('andi@example.com');
        $response->assertSee('Siswa');
        $response->assertSee('Informasi Siswa');
        $response->assertSee('0071234567');
        $response->assertSee('24001');
        $response->assertSee('Bapak Santoso');
        $response->assertSee('089876543210');
        $response->assertSee(route('admin.students.index'), false);
    }

    public function test_edit_button_is_rendered_only_once_inside_the_profile_card_footer(): void
    {
        $student = $this->createStudent();

        $response = $this->actingAs($this->admin())->get(route('admin.students.show', $student));

        $response->assertOk();

        $content = $response->getContent();
        $editUrl = route('admin.students.edit', $student);

        $this->assertSame(1, substr_count($content, $editUrl), 'The edit URL should appear exactly once.');

        $response->assertSee('<div class="card-body border-top">', false);
        $response->assertSee('btn btn-primary w-100', false);
        $response->assertSeeInOrder(['Kembali', 'Siswa', 'Ubah Data Siswa']);
        $response->assertSee('Ubah Data Siswa');
        $response->assertSeeInOrder(['andi@example.com', $editUrl]);
    }

    private function createStudent(array $attributes = []): StudentProfile
    {
        $user = User::factory()->create([
            'name' => 'Andi Saputra',
            'email' => 'andi@example.com',
            'role' => 'student',
            'is_active' => true,
        ]);

        return StudentProfile::create([
            'user_id' => $user->id,
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
