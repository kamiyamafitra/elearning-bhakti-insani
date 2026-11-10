<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class ForcedPasswordChangeTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    public function test_users_flagged_for_a_password_change_are_sent_to_the_password_page(): void
    {
        $student = $this->makeStudent($this->makeClass(), 'Andi', '24001');
        $student->update(['must_change_password' => true]);

        $this->actingAs($student)->get(route('student.dashboard'))->assertRedirect(route('password.edit'));
        $this->actingAs($student)->get(route('student.subjects.index'))->assertRedirect(route('password.edit'));

        $this->actingAs($student)->get(route('password.edit'))
            ->assertOk()
            ->assertSee('Password Anda baru saja diatur ulang oleh administrator.');
    }

    public function test_flag_applies_to_teachers_and_admins_too(): void
    {
        $teacher = $this->makeTeacher();
        $teacher->update(['must_change_password' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'must_change_password' => true]);

        $this->actingAs($teacher)->get(route('teacher.dashboard'))->assertRedirect(route('password.edit'));
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('password.edit'));
    }

    public function test_users_without_the_flag_are_not_redirected(): void
    {
        $student = $this->makeStudent($this->makeClass());

        $this->actingAs($student)->get(route('student.dashboard'))->assertOk();
        $this->actingAs($student)->get(route('password.edit'))
            ->assertOk()
            ->assertDontSee('Password Anda baru saja diatur ulang oleh administrator.');
    }

    public function test_changing_the_password_clears_the_flag_and_opens_the_dashboard(): void
    {
        $student = $this->makeStudent($this->makeClass(), 'Andi', '24001');
        $student->update(['password' => Hash::make('24001'), 'must_change_password' => true]);

        $response = $this->actingAs($student)->put(route('password.update'), [
            'current_password' => '24001',
            'password' => 'rahasia-baru-1',
            'password_confirmation' => 'rahasia-baru-1',
        ]);

        $response->assertRedirect(route('student.dashboard'));
        $response->assertSessionHas('success', 'Password berhasil diperbarui. Selamat datang!');
        $this->assertFalse($student->fresh()->must_change_password);

        $this->actingAs($student->fresh())->get(route('student.dashboard'))->assertOk();
    }

    public function test_flagged_user_can_still_log_out(): void
    {
        $student = $this->makeStudent($this->makeClass());
        $student->update(['must_change_password' => true]);

        $this->actingAs($student)->post(route('logout'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_imported_accounts_must_change_their_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.students.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('siswa.csv', "nama,nis\nAndi,24001"),
        ]);
        $this->actingAs($admin)->post(route('admin.teachers.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('guru.csv', "nama,nip\nBudi,198001012005011001"),
        ]);

        $this->assertTrue(User::where('name', 'Andi')->firstOrFail()->must_change_password);
        $this->assertTrue(User::where('name', 'Budi')->firstOrFail()->must_change_password);
    }
}
