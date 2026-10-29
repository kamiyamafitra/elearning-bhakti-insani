<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateFormLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_create_form_displays_every_field_and_consistent_actions(): void
    {
        $class = $this->makeClass('X TKJ 1');

        $response = $this->actingAs($this->admin())->get(route('admin.students.create'));

        $response->assertOk();
        $response->assertSee('Tambah Data Siswa');
        $response->assertSee('Data Akun');
        $response->assertSee('Data Siswa');
        $response->assertSee('class="card shadow-sm"', false);
        $response->assertSee('class="admin-form"', false);
        $response->assertSee('admin-form__section-title', false);
        $response->assertSee('admin-form__actions', false);
        $response->assertSee('action="'.route('admin.students.store').'"', false);
        $response->assertSee('X TKJ 1');

        foreach ([
            'name', 'email', 'password', 'password_confirmation', 'nisn', 'nis', 'class_id',
            'gender', 'birth_date', 'phone', 'address', 'parent_name', 'parent_phone',
        ] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }

        $response->assertSee('for="name"', false);
        $response->assertSee('for="parent_phone"', false);
        $response->assertSee('<button type="submit" class="btn btn-primary">Simpan</button>', false);
        $response->assertSee('class="btn btn-outline-secondary">Batal</a>', false);
        $response->assertSee(route('admin.students.index'), false);
    }

    public function test_teacher_create_form_displays_every_field_and_consistent_actions(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.teachers.create'));

        $response->assertOk();
        $response->assertSee('Tambah Data Guru');
        $response->assertSee('Data Akun');
        $response->assertSee('Data Guru');
        $response->assertSee('class="card shadow-sm"', false);
        $response->assertSee('class="admin-form"', false);
        $response->assertSee('action="'.route('admin.teachers.store').'"', false);

        foreach ([
            'name', 'email', 'password', 'password_confirmation', 'nip', 'phone', 'address',
        ] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }

        $response->assertSee('for="nip"', false);
        $response->assertSee('<button type="submit" class="btn btn-primary">Simpan</button>', false);
        $response->assertSee('class="btn btn-outline-secondary">Batal</a>', false);
        $response->assertSee(route('admin.teachers.index'), false);
    }

    public function test_class_create_form_displays_every_field_and_consistent_actions(): void
    {
        $academicYear = $this->makeAcademicYear();

        $response = $this->actingAs($this->admin())->get(route('admin.classes.create'));

        $response->assertOk();
        $response->assertSee('Tambah Kelas');
        $response->assertSee('Informasi Kelas');
        $response->assertSee('2025/2026');
        $response->assertSee('class="card shadow-sm"', false);
        $response->assertSee('class="admin-form"', false);
        $response->assertSee('action="'.route('admin.classes.store').'"', false);

        foreach (['academic_year_id', 'name', 'code', 'level'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }

        $response->assertSee('value="'.$academicYear->id.'"', false);
        $response->assertSee('for="code"', false);
        $response->assertSee('<button type="submit" class="btn btn-primary">Simpan</button>', false);
        $response->assertSee('class="btn btn-outline-secondary">Batal</a>', false);
        $response->assertSee(route('admin.classes.index'), false);
    }

    public function test_student_create_form_preserves_old_values_after_validation_error(): void
    {
        $class = $this->makeClass('X TKJ 1');

        $this->actingAs($this->admin())->post(route('admin.students.store'), [
            'name' => 'Budi Santoso',
            'email' => '',
            'password' => 'rahasia',
            'password_confirmation' => 'rahasia',
            'nisn' => '1234567890',
            'class_id' => $class->id,
            'gender' => 'L',
            'parent_name' => 'Sutrisno',
            'parent_phone' => '08123456789',
        ])->assertSessionHasErrors('email');

        $response = $this->actingAs($this->admin())->get(route('admin.students.create'));

        $response->assertOk();
        $response->assertSee('value="Budi Santoso"', false);
        $response->assertSee('value="1234567890"', false);
        $response->assertSee('value="'.$class->id.'" selected', false);
        $response->assertSee('value="Sutrisno"', false);
        $response->assertSee('value="08123456789"', false);
    }

    public function test_teacher_create_form_preserves_old_values_after_validation_error(): void
    {
        $this->actingAs($this->admin())->post(route('admin.teachers.store'), [
            'name' => 'Dewi Lestari',
            'email' => '',
            'password' => 'rahasia',
            'password_confirmation' => 'rahasia',
            'nip' => '1987654321',
            'phone' => '08129876543',
            'address' => 'Bogor',
        ])->assertSessionHasErrors('email');

        $response = $this->actingAs($this->admin())->get(route('admin.teachers.create'));

        $response->assertOk();
        $response->assertSee('value="Dewi Lestari"', false);
        $response->assertSee('value="1987654321"', false);
        $response->assertSee('value="08129876543"', false);
        $response->assertSee('Bogor');
    }

    public function test_class_create_form_preserves_old_values_after_validation_error(): void
    {
        $academicYear = $this->makeAcademicYear();

        $this->actingAs($this->admin())->post(route('admin.classes.store'), [
            'academic_year_id' => $academicYear->id,
            'name' => 'X TKJ 2',
            'code' => '',
            'level' => 'X',
        ])->assertSessionHasErrors('code');

        $response = $this->actingAs($this->admin())->get(route('admin.classes.create'));

        $response->assertOk();
        $response->assertSee('value="X TKJ 2"', false);
        $response->assertSee('value="X"', false);
        $response->assertSee('value="'.$academicYear->id.'" selected', false);
    }

    public function test_student_can_still_be_created_through_the_existing_route(): void
    {
        $class = $this->makeClass('X TKJ 1');

        $response = $this->actingAs($this->admin())->post(route('admin.students.store'), [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'rahasia',
            'password_confirmation' => 'rahasia',
            'nisn' => '1234567890',
            'nis' => '1001',
            'class_id' => $class->id,
            'gender' => 'L',
            'birth_date' => '2009-01-15',
            'phone' => '08123456789',
            'address' => 'Bogor',
            'parent_name' => 'Sutrisno',
            'parent_phone' => '08123456780',
        ]);

        $response->assertRedirect(route('admin.students.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'role' => 'student',
        ]);
        $this->assertDatabaseHas('student_profiles', [
            'nisn' => '1234567890',
            'nis' => '1001',
            'class_id' => $class->id,
            'parent_name' => 'Sutrisno',
        ]);
        $this->assertSame(1, StudentProfile::count());
    }

    public function test_teacher_can_still_be_created_through_the_existing_route(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.teachers.store'), [
            'name' => 'Dewi Lestari',
            'email' => 'dewi@example.com',
            'password' => 'rahasia',
            'password_confirmation' => 'rahasia',
            'nip' => '1987654321',
            'phone' => '08129876543',
            'address' => 'Bogor',
        ]);

        $response->assertRedirect(route('admin.teachers.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'name' => 'Dewi Lestari',
            'email' => 'dewi@example.com',
            'role' => 'teacher',
        ]);
        $this->assertDatabaseHas('teacher_profiles', [
            'nip' => '1987654321',
            'phone' => '08129876543',
            'address' => 'Bogor',
        ]);
        $this->assertSame(1, TeacherProfile::count());
    }

    public function test_class_can_still_be_created_through_the_existing_route(): void
    {
        $academicYear = $this->makeAcademicYear();

        $response = $this->actingAs($this->admin())->post(route('admin.classes.store'), [
            'academic_year_id' => $academicYear->id,
            'name' => 'XI TKJ 1',
            'code' => 'XI-TKJ-1',
            'level' => 'XI',
        ]);

        $response->assertRedirect(route('admin.classes.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('classes', [
            'academic_year_id' => $academicYear->id,
            'name' => 'XI TKJ 1',
            'code' => 'XI-TKJ-1',
            'level' => 'XI',
        ]);
    }

    private function makeAcademicYear(): AcademicYear
    {
        return AcademicYear::firstOrCreate(
            ['name' => '2025/2026'],
            [
                'start_date' => '2025-07-14',
                'end_date' => '2026-06-30',
                'is_active' => true,
            ],
        );
    }

    private function makeClass(string $name): Classes
    {
        return Classes::create([
            'academic_year_id' => $this->makeAcademicYear()->id,
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
