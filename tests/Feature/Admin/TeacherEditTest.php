<?php

namespace Tests\Feature\Admin;

use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeacherEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_the_teacher_edit_page(): void
    {
        $teacher = $this->createTeacher();

        $this->get(route('admin.teachers.edit', $teacher))->assertRedirect(route('login'));
    }

    public function test_edit_page_displays_the_selected_teacher_data_and_update_form(): void
    {
        $teacher = $this->createTeacher([
            'nip' => '198001012005011001',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 1',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.teachers.edit', $teacher));

        $response->assertOk();
        $response->assertSee('Ubah Data Guru');
        $response->assertSee('value="'.$teacher->user->name.'"', false);
        $response->assertSee('value="'.$teacher->user->email.'"', false);
        $response->assertSee('value="198001012005011001"', false);
        $response->assertSee('value="081234567890"', false);
        $response->assertSee('Jl. Merdeka No. 1');
        $response->assertSee('action="'.route('admin.teachers.update', $teacher).'"', false);
        $response->assertSee('name="_method" value="PUT"', false);
        $response->assertSee('name="_token"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="password_confirmation"', false);
        $response->assertSee(route('admin.teachers.index'), false);
    }

    public function test_teacher_can_be_updated_without_changing_the_password(): void
    {
        $teacher = $this->createTeacher([
            'nip' => '198001012005011001',
            'phone' => '081234567890',
            'address' => 'Alamat lama',
        ]);

        $response = $this->actingAs($this->admin())->put(route('admin.teachers.update', $teacher), [
            'name' => 'Budi Santoso Baru',
            'email' => 'budi.baru@example.com',
            'password' => '',
            'password_confirmation' => '',
            'nip' => '199002022010012002',
            'phone' => '089876543210',
            'address' => 'Alamat baru',
        ]);

        $response->assertRedirect(route('admin.teachers.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $teacher->user_id,
            'name' => 'Budi Santoso Baru',
            'email' => 'budi.baru@example.com',
        ]);

        $this->assertDatabaseHas('teacher_profiles', [
            'id' => $teacher->id,
            'nip' => '199002022010012002',
            'phone' => '089876543210',
            'address' => 'Alamat baru',
        ]);

        $this->assertTrue(Hash::check('password', $teacher->user->fresh()->password));
    }

    public function test_teacher_password_is_updated_only_when_a_new_password_is_provided(): void
    {
        $teacher = $this->createTeacher();

        $response = $this->actingAs($this->admin())->put(route('admin.teachers.update', $teacher), [
            'name' => $teacher->user->name,
            'email' => $teacher->user->email,
            'password' => 'kata-sandi-baru1',
            'password_confirmation' => 'kata-sandi-baru1',
        ]);

        $response->assertRedirect(route('admin.teachers.index'));
        $response->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('kata-sandi-baru1', $teacher->user->fresh()->password));
    }

    public function test_update_validation_errors_are_displayed_and_old_values_are_kept(): void
    {
        $teacher = $this->createTeacher();
        $otherUser = User::factory()->create([
            'role' => 'teacher',
            'is_active' => true,
        ]);

        $page = $this->actingAs($this->admin())
            ->from(route('admin.teachers.edit', $teacher))
            ->followingRedirects()
            ->put(route('admin.teachers.update', $teacher), [
                'name' => 'Nama Tidak Tersimpan',
                'email' => $otherUser->email,
                'password' => 'pendek',
                'password_confirmation' => 'beda',
            ]);

        $page->assertOk();
        $page->assertSee('Nama Tidak Tersimpan');
        $page->assertSee('is-invalid', false);
        $page->assertSee('invalid-feedback', false);

        $this->assertDatabaseHas('users', [
            'id' => $teacher->user_id,
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTeacher(array $attributes = []): TeacherProfile
    {
        $user = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'role' => 'teacher',
            'is_active' => true,
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
