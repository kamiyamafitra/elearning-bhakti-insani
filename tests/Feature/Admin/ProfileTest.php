<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_user_management_page_is_gone(): void
    {
        $this->actingAs($this->admin())->get('/admin/users')->assertNotFound();
    }

    public function test_profile_menu_link_opens_the_admin_profile_page(): void
    {
        $admin = $this->admin(['name' => 'Kepala Admin']);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertSee(route('admin.profile.edit'), false);

        $this->actingAs($admin)->get(route('admin.profile.edit'))
            ->assertOk()
            ->assertSee('value="Kepala Admin"', false)
            ->assertSee('action="'.route('admin.profile.update').'"', false)
            ->assertSee('action="'.route('password.update').'"', false);
    }

    public function test_admin_can_update_name_and_email(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.profile.update'), [
            'name' => 'Admin Baru',
            'email' => 'admin.baru@example.com',
        ])->assertRedirect(route('admin.profile.edit'))->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'name' => 'Admin Baru', 'email' => 'admin.baru@example.com']);
    }

    public function test_email_must_be_unique_and_role_cannot_be_changed(): void
    {
        $admin = $this->admin();
        User::factory()->create(['email' => 'dipakai@example.com']);

        $this->actingAs($admin)->put(route('admin.profile.update'), [
            'name' => 'Admin',
            'email' => 'dipakai@example.com',
        ])->assertSessionHasErrors('email');

        $this->actingAs($admin)->put(route('admin.profile.update'), [
            'name' => 'Admin',
            'email' => $admin->email,
            'role' => 'student',
            'is_active' => false,
        ]);

        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_non_admins_cannot_open_the_admin_profile(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);

        $this->actingAs($teacher)->get(route('admin.profile.edit'))->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function admin(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'admin',
            'is_active' => true,
        ], $attributes));
    }
}
