<?php

namespace Tests\Feature\Admin;

use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_the_teacher_detail_page(): void
    {
        $teacher = $this->createTeacher();

        $this->get(route('admin.teachers.show', $teacher))->assertRedirect(route('login'));
    }

    public function test_detail_page_displays_the_selected_teacher_information(): void
    {
        $teacher = $this->createTeacher([
            'nip' => '198001012005011001',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 1',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.teachers.show', $teacher));

        $response->assertOk();
        $response->assertSee('Detail Data Guru');
        $response->assertSee('Budi Santoso');
        $response->assertSee('BS');
        $response->assertSee('budi@example.com');
        $response->assertSee('198001012005011001');
        $response->assertSee('081234567890');
        $response->assertSee('Jl. Merdeka No. 1');
        $response->assertSee('Aktif');
        $response->assertSee('Kembali');
        $response->assertSee(route('admin.teachers.index'), false);
        $response->assertSee(route('admin.teachers.edit', $teacher), false);
        $response->assertDontSee('href="'.route('admin.teachers.destroy', $teacher).'"', false);
        $response->assertDontSee('name="_method" value="DELETE"', false);
    }

    public function test_detail_page_shows_inactive_status_and_placeholders_for_missing_fields(): void
    {
        $teacher = $this->createTeacher(active: false);

        $response = $this->actingAs($this->admin())->get(route('admin.teachers.show', $teacher));

        $response->assertOk();
        $response->assertSee('Nonaktif');
        $response->assertSeeInOrder(['NIP', '-']);
        $response->assertSeeInOrder(['No. Telepon', '-']);
        $response->assertSeeInOrder(['Alamat', '-']);
    }

    public function test_teacher_list_delete_action_keeps_csrf_delete_method_and_confirmation(): void
    {
        $teacher = $this->createTeacher();

        $response = $this->actingAs($this->admin())->get(route('admin.teachers.index'));

        $response->assertOk();
        $response->assertSee('action="'.route('admin.teachers.destroy', $teacher).'"', false);
        $response->assertSee('name="_method" value="DELETE"', false);
        $response->assertSee("confirm('Yakin ingin menghapus data guru ini?')", false);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTeacher(array $attributes = [], bool $active = true): TeacherProfile
    {
        $user = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'role' => 'teacher',
            'is_active' => $active,
        ]);

        return TeacherProfile::create([
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
