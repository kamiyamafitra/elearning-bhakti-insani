<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    public function test_non_admins_cannot_reset_passwords(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent($class);
        $teacher = $this->makeTeacher();

        $this->post(route('admin.students.reset-password', $student->studentProfile))->assertRedirect(route('login'));

        $this->actingAs($teacher)->post(route('admin.students.reset-password', $student->studentProfile))->assertForbidden();
        $this->actingAs($teacher)->post(route('admin.classes.reset-passwords', $class))->assertForbidden();

        $this->assertFalse($student->fresh()->must_change_password);
    }

    public function test_detail_pages_show_the_reset_buttons(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent($class);
        $teacher = $this->makeTeacher();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.students.show', $student->studentProfile))
            ->assertSee('action="'.route('admin.students.reset-password', $student->studentProfile).'"', false);

        $this->actingAs($admin)->get(route('admin.teachers.show', $teacher->teacherProfile))
            ->assertSee('action="'.route('admin.teachers.reset-password', $teacher->teacherProfile).'"', false);

        $this->actingAs($admin)->get(route('admin.classes.show', $class))
            ->assertSee('Reset Password Semua Siswa');
    }

    public function test_student_password_is_reset_to_nis_and_must_be_changed(): void
    {
        $student = $this->makeStudent($this->makeClass(), 'Andi Saputra', '24001');

        $response = $this->actingAs($this->admin())
            ->from(route('admin.students.show', $student->studentProfile))
            ->post(route('admin.students.reset-password', $student->studentProfile));

        $response->assertRedirect(route('admin.students.show', $student->studentProfile));
        $response->assertSessionHas('success', 'Password Andi Saputra direset menjadi NIS. Siswa wajib membuat password baru saat masuk.');

        $student->refresh();
        $this->assertTrue(Hash::check('24001', $student->password));
        $this->assertTrue($student->must_change_password);
    }

    public function test_teacher_password_is_reset_to_nip(): void
    {
        $teacher = $this->makeTeacher('Budi Santoso', '198001012005011001');

        $this->actingAs($this->admin())
            ->post(route('admin.teachers.reset-password', $teacher->teacherProfile))
            ->assertSessionHas('success');

        $teacher->refresh();
        $this->assertTrue(Hash::check('198001012005011001', $teacher->password));
        $this->assertTrue($teacher->must_change_password);
    }

    public function test_reset_is_refused_when_nis_is_missing(): void
    {
        $student = $this->makeStudent($this->makeClass());
        $student->studentProfile->update(['nis' => null]);
        $originalHash = $student->password;

        $this->actingAs($this->admin())
            ->post(route('admin.students.reset-password', $student->studentProfile))
            ->assertSessionHas('error');

        $this->assertSame($originalHash, $student->fresh()->password);
        $this->assertFalse($student->fresh()->must_change_password);
    }

    public function test_class_reset_covers_students_with_nis_and_skips_the_rest(): void
    {
        $class = $this->makeClass('X TKJ 1');
        $first = $this->makeStudent($class, 'Andi', '24001');
        $second = $this->makeStudent($class, 'Bunga', '24002');
        $withoutNis = $this->makeStudent($class, 'Citra', '24003');
        $withoutNis->studentProfile->update(['nis' => null]);
        $otherClassStudent = $this->makeStudent($this->makeClass('XI TKJ 1'), 'Dedi', '23001');

        $response = $this->actingAs($this->admin())->post(route('admin.classes.reset-passwords', $class));

        $response->assertSessionHas('success', 'Password 2 siswa X TKJ 1 direset menjadi NIS masing-masing. 1 siswa dilewati karena belum memiliki NIS.');

        $this->assertTrue(Hash::check('24001', $first->fresh()->password));
        $this->assertTrue(Hash::check('24002', $second->fresh()->password));
        $this->assertTrue($first->fresh()->must_change_password);
        $this->assertFalse($withoutNis->fresh()->must_change_password);
        $this->assertFalse($otherClassStudent->fresh()->must_change_password);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
