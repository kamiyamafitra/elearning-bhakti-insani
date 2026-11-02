<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    public function test_guests_cannot_update_a_password(): void
    {
        $this->put(route('password.update'))->assertRedirect(route('login'));
    }

    public function test_student_and_teacher_profile_pages_show_the_password_form(): void
    {
        $student = $this->makeStudent($this->makeClass());
        $teacher = $this->makeTeacher();

        foreach ([[$student, 'student.profile.edit'], [$teacher, 'teacher.profile.edit']] as [$user, $routeName]) {
            $this->actingAs($user)->get(route($routeName))
                ->assertOk()
                ->assertSee('Ubah Password')
                ->assertSee('action="'.route('password.update').'"', false)
                ->assertSee('name="current_password"', false)
                ->assertSee('name="password_confirmation"', false);
        }
    }

    public function test_user_can_change_their_password_with_the_correct_current_password(): void
    {
        $student = $this->makeStudent($this->makeClass(), 'Andi', '24001');
        $student->update(['password' => Hash::make('24001')]);

        $response = $this->actingAs($student)
            ->from(route('student.profile.edit'))
            ->put(route('password.update'), [
                'current_password' => '24001',
                'password' => 'rahasia-baru',
                'password_confirmation' => 'rahasia-baru',
            ]);

        $response->assertRedirect(route('student.profile.edit'));
        $response->assertSessionHas('success', 'Password berhasil diperbarui.');
        $this->assertTrue(Hash::check('rahasia-baru', $student->fresh()->password));
    }

    public function test_new_password_can_be_used_to_log_in(): void
    {
        $teacher = $this->makeTeacher('Budi', '198001012005011001');
        $teacher->update(['password' => Hash::make('198001012005011001')]);

        $this->actingAs($teacher)->put(route('password.update'), [
            'current_password' => '198001012005011001',
            'password' => 'guru-baru-123',
            'password_confirmation' => 'guru-baru-123',
        ]);

        $this->post(route('logout'));

        $this->post(route('login'), [
            'username' => '198001012005011001',
            'password' => 'guru-baru-123',
        ])->assertRedirect(route('teacher.dashboard'));

        $this->assertAuthenticatedAs($teacher);
    }

    public function test_password_is_not_changed_when_validation_fails(): void
    {
        $user = User::factory()->create([
            'role' => 'teacher',
            'is_active' => true,
            'password' => Hash::make('lama-123'),
        ]);

        $cases = [
            'current_password' => ['current_password' => 'salah', 'password' => 'baru-123', 'password_confirmation' => 'baru-123'],
            'password' => ['current_password' => 'lama-123', 'password' => 'baru-123', 'password_confirmation' => 'beda-123'],
        ];

        foreach ($cases as $field => $payload) {
            $this->actingAs($user)
                ->put(route('password.update'), $payload)
                ->assertSessionHasErrorsIn('updatePassword', $field);
        }

        $this->actingAs($user)
            ->put(route('password.update'), ['current_password' => 'lama-123', 'password' => '12345', 'password_confirmation' => '12345'])
            ->assertSessionHasErrorsIn('updatePassword', ['password' => 'Password baru minimal 6 karakter.']);

        $this->actingAs($user)
            ->put(route('password.update'), ['current_password' => 'lama-123', 'password' => 'lama-123', 'password_confirmation' => 'lama-123'])
            ->assertSessionHasErrorsIn('updatePassword', ['password' => 'Password baru harus berbeda dari password saat ini.']);

        $this->assertTrue(Hash::check('lama-123', $user->fresh()->password));
    }

    public function test_password_errors_are_shown_on_the_profile_page_without_affecting_the_profile_form(): void
    {
        $student = $this->makeStudent($this->makeClass());

        $page = $this->actingAs($student)
            ->from(route('student.profile.edit'))
            ->followingRedirects()
            ->put(route('password.update'), [
                'current_password' => 'salah',
                'password' => 'baru-123',
                'password_confirmation' => 'baru-123',
            ]);

        $page->assertOk();
        $page->assertSee('Password saat ini tidak sesuai.');
        $page->assertDontSee('id="name" name="name" class="form-control is-invalid"', false);
    }
}
