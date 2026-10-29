<?php

namespace Tests\Feature\Admin;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class ClassAssignStudentsTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_the_assign_students_page(): void
    {
        $class = $this->makeClass('10 PPLG');

        $this->get(route('admin.classes.assign-students', $class))->assertRedirect(route('login'));
    }

    public function test_non_admin_user_receives_forbidden_response(): void
    {
        $class = $this->makeClass('10 PPLG');
        $teacher = $this->makeTeacher();

        $this->actingAs($teacher)
            ->get(route('admin.classes.assign-students', $class))
            ->assertForbidden();
    }

    public function test_page_lists_assigned_and_available_students(): void
    {
        $class = $this->makeClass('10 PPLG');
        $otherClass = $this->makeClass('11 PPLG');

        $assigned = $this->makeStudent($class, 'Siswa Kelas Ini', '1001');
        $available = $this->makeStudent($otherClass, 'Siswa Kelas Lain', '1002');

        $response = $this->actingAs($this->admin())
            ->get(route('admin.classes.assign-students', $class));

        $response->assertOk();
        $response->assertSee('Tetapkan Siswa');
        $response->assertSee('10 PPLG');
        $response->assertSee('Siswa di Kelas Ini');
        $response->assertSee('Tambahkan Siswa');
        $response->assertSee('Siswa Kelas Ini');
        $response->assertSee('1001');
        $response->assertSee('Siswa Kelas Lain');
        $response->assertSee('1002');
        $response->assertSee('11 PPLG');
        $response->assertDontSee(route('admin.students.show', $available->studentProfile), false);
        $response->assertSee(route('admin.students.show', $assigned->studentProfile), false);
    }

    public function test_page_shows_an_empty_state_when_the_class_has_no_students(): void
    {
        $class = $this->makeClass('10 PPLG');
        $this->makeStudent($this->makeClass('11 PPLG'), 'Siswa Lain', '1002');

        $response = $this->actingAs($this->admin())
            ->get(route('admin.classes.assign-students', $class));

        $response->assertOk();
        $response->assertSee('Belum ada siswa di kelas ini.');
        $response->assertDontSee('Semua siswa sudah berada di kelas ini.');
    }

    public function test_page_shows_a_completion_state_when_all_students_are_in_the_class(): void
    {
        $class = $this->makeClass('10 PPLG');
        $this->makeStudent($class, 'Siswa Satu', '1001');

        $response = $this->actingAs($this->admin())
            ->get(route('admin.classes.assign-students', $class));

        $response->assertOk();
        $response->assertSee('Semua siswa sudah berada di kelas ini.');
        $response->assertDontSee('Tetapkan ke Kelas');
    }

    public function test_students_can_be_reassigned_to_the_class(): void
    {
        $class = $this->makeClass('10 PPLG');
        $otherClass = $this->makeClass('11 PPLG');
        $student = $this->makeStudent($otherClass, 'Siswa Pindah', '1002');

        $response = $this->actingAs($this->admin())->post(
            route('admin.classes.assign-students.store', $class),
            ['student_profile_ids' => [$student->studentProfile->id]],
        );

        $response->assertRedirect(route('admin.classes.show', $class));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('student_profiles', [
            'id' => $student->studentProfile->id,
            'class_id' => $class->id,
        ]);
    }

    public function test_multiple_students_can_be_assigned_in_one_request(): void
    {
        $class = $this->makeClass('10 PPLG');
        $otherClass = $this->makeClass('11 PPLG');
        $first = $this->makeStudent($otherClass, 'Siswa Satu', '1001');
        $second = $this->makeStudent($otherClass, 'Siswa Dua', '1002');

        $this->actingAs($this->admin())->post(
            route('admin.classes.assign-students.store', $class),
            [
                'student_profile_ids' => [
                    $first->studentProfile->id,
                    $second->studentProfile->id,
                ],
            ],
        )->assertSessionHasNoErrors();

        $this->assertSame(2, StudentProfile::where('class_id', $class->id)->count());
    }

    public function test_at_least_one_student_is_required(): void
    {
        $class = $this->makeClass('10 PPLG');

        $this->actingAs($this->admin())
            ->post(route('admin.classes.assign-students.store', $class), ['student_profile_ids' => []])
            ->assertSessionHasErrors('student_profile_ids');
    }

    public function test_a_student_profile_must_exist(): void
    {
        $class = $this->makeClass('10 PPLG');

        $this->actingAs($this->admin())
            ->post(route('admin.classes.assign-students.store', $class), ['student_profile_ids' => [9999]])
            ->assertSessionHasErrors('student_profile_ids.0');
    }

    public function test_assigning_students_does_not_change_their_identity_fields(): void
    {
        $class = $this->makeClass('10 PPLG');
        $otherClass = $this->makeClass('11 PPLG');
        $student = $this->makeStudent($otherClass, 'Siswa Pindah', '1002');

        $this->actingAs($this->admin())->post(
            route('admin.classes.assign-students.store', $class),
            ['student_profile_ids' => [$student->studentProfile->id]],
        );

        $this->assertDatabaseHas('student_profiles', [
            'id' => $student->studentProfile->id,
            'nis' => '1002',
            'class_id' => $class->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'role' => 'student',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }
}
