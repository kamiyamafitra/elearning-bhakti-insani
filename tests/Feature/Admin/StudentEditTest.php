<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_the_student_edit_page(): void
    {
        $student = $this->createStudent();

        $this->get(route('admin.students.edit', $student))->assertRedirect(route('login'));
    }

    public function test_edit_page_displays_every_student_field_and_the_update_form(): void
    {
        $class = $this->createClass('X TKJ 1');
        $otherClass = $this->createClass('XI TKJ 2');

        $student = $this->createStudent([
            'nisn' => '0071234567',
            'nis' => '24001',
            'class_id' => $class->id,
            'gender' => 'P',
            'birth_date' => '2010-05-17',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 1',
            'parent_name' => 'Bapak Santoso',
            'parent_phone' => '089876543210',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.students.edit', $student));

        $response->assertOk();
        $response->assertSee('Ubah Data Siswa');
        $response->assertSee('Data Akun');
        $response->assertSee('Data Siswa');
        $response->assertSee('Data Orang Tua/Wali');
        $response->assertSee('name="name"', false);
        $response->assertSee('value="Andi Saputra"', false);
        $response->assertSee('value="andi@example.com"', false);
        $response->assertSee('value="0071234567"', false);
        $response->assertSee('value="24001"', false);
        $response->assertSee('value="'.$class->id.'" selected', false);
        $response->assertSee($otherClass->name);
        $response->assertSee('<option value="P" selected>', false);
        $response->assertSee('value="2010-05-17"', false);
        $response->assertSee('value="081234567890"', false);
        $response->assertSee('Jl. Merdeka No. 1');
        $response->assertSee('value="Bapak Santoso"', false);
        $response->assertSee('value="089876543210"', false);
        $response->assertSee('action="'.route('admin.students.update', $student).'"', false);
        $response->assertSee('name="_method" value="PUT"', false);
        $response->assertSee('name="_token"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="password_confirmation"', false);
        $response->assertSee(route('admin.students.index'), false);
    }

    public function test_student_can_be_updated_without_changing_the_password(): void
    {
        $oldClass = $this->createClass('X TKJ 1');
        $newClass = $this->createClass('XI TKJ 2');

        $student = $this->createStudent([
            'nisn' => '0071234567',
            'nis' => '24001',
            'class_id' => $oldClass->id,
            'gender' => 'L',
        ]);

        $response = $this->actingAs($this->admin())->put(route('admin.students.update', $student), [
            'name' => 'Andi Saputra Baru',
            'email' => 'andi.baru@example.com',
            'password' => '',
            'password_confirmation' => '',
            'nisn' => '0079999999',
            'nis' => '24999',
            'class_id' => $newClass->id,
            'gender' => 'P',
            'birth_date' => '2011-08-20',
            'phone' => '081200000000',
            'address' => 'Alamat baru',
            'parent_name' => 'Ibu Lestari',
            'parent_phone' => '081300000000',
        ]);

        $response->assertRedirect(route('admin.students.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $student->user_id,
            'name' => 'Andi Saputra Baru',
            'email' => 'andi.baru@example.com',
        ]);

        $this->assertDatabaseHas('student_profiles', [
            'id' => $student->id,
            'nisn' => '0079999999',
            'nis' => '24999',
            'class_id' => $newClass->id,
            'gender' => 'P',
            'phone' => '081200000000',
            'address' => 'Alamat baru',
            'parent_name' => 'Ibu Lestari',
            'parent_phone' => '081300000000',
        ]);

        $this->assertDatabaseHas('student_profiles', [
            'id' => $student->id,
            'birth_date' => '2011-08-20 00:00:00',
        ]);

        $this->assertTrue(Hash::check('password', $student->user->fresh()->password));
    }

    public function test_student_password_is_updated_only_when_a_new_password_is_provided(): void
    {
        $student = $this->createStudent();

        $response = $this->actingAs($this->admin())->put(route('admin.students.update', $student), [
            'name' => $student->user->name,
            'email' => $student->user->email,
            'password' => 'kata-sandi-baru1',
            'password_confirmation' => 'kata-sandi-baru1',
        ]);

        $response->assertRedirect(route('admin.students.index'));
        $response->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('kata-sandi-baru1', $student->user->fresh()->password));
    }

    public function test_update_validation_errors_are_displayed_and_old_values_are_kept(): void
    {
        $student = $this->createStudent();
        $otherUser = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);

        $page = $this->actingAs($this->admin())
            ->from(route('admin.students.edit', $student))
            ->followingRedirects()
            ->put(route('admin.students.update', $student), [
                'name' => 'Nama Tidak Tersimpan',
                'email' => $otherUser->email,
                'password' => 'pendek',
                'password_confirmation' => 'beda',
                'gender' => 'X',
            ]);

        $page->assertOk();
        $page->assertSee('Nama Tidak Tersimpan');
        $page->assertSee('is-invalid', false);
        $page->assertSee('invalid-feedback', false);

        $this->assertDatabaseHas('users', [
            'id' => $student->user_id,
            'name' => 'Andi Saputra',
            'email' => 'andi@example.com',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
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

    private function createClass(string $name): Classes
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
