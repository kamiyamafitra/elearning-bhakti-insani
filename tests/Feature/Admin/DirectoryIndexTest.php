<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectoryIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_for_directory_pages(): void
    {
        $this->get(route('admin.students.index'))->assertRedirect(route('login'));
        $this->get(route('admin.teachers.index'))->assertRedirect(route('login'));
        $this->get(route('admin.classes.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_users_receive_forbidden_response_for_directory_pages(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'is_active' => true,
        ]);

        $this->actingAs($teacher)->get(route('admin.students.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.teachers.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.classes.index'))->assertForbidden();
    }

    public function test_student_index_displays_real_counts_and_student_details(): void
    {
        $class = $this->createClass('X TKJ 1', '10');

        $student = $this->createStudent('Andi Saputra', 'andi@example.com', [
            'nis' => '24001',
            'nisn' => '0071234567',
            'class_id' => $class->id,
        ]);

        $this->createStudent('Bunga Lestari', 'bunga@example.com');

        $response = $this->actingAs($this->admin())->get(route('admin.students.index'));

        $response->assertOk();
        $response->assertViewHas('totalStudents', 2);
        $response->assertViewHas('studentsWithClass', 1);
        $response->assertViewHas('classesWithStudents', 1);

        $response->assertSee('Data Siswa');
        $response->assertSee('2 Siswa');
        $response->assertSeeInOrder(['Total Siswa', '2']);
        $response->assertSeeInOrder(['Sudah Dapat Kelas', '1']);
        $response->assertSee('Andi Saputra');
        $response->assertSee('andi@example.com');
        $response->assertSee('AS');
        $response->assertSee('24001');
        $response->assertSee('0071234567');
        $response->assertSee('X TKJ 1');
        $response->assertSee('Belum ada kelas');
        $response->assertSee(route('admin.students.show', $student), false);
        $response->assertSee(route('admin.students.edit', $student), false);
        $response->assertSee(route('admin.students.create'), false);
        $response->assertSee("confirm('Yakin ingin menghapus data siswa ini?')", false);
    }

    public function test_students_can_be_searched_by_name_email_nis_and_nisn(): void
    {
        $this->createStudent('Andi Saputra', 'andi@example.com', [
            'nis' => '24001',
            'nisn' => '0071234567',
        ]);

        $this->createStudent('Bunga Lestari', 'bunga@example.com', [
            'nis' => '24002',
            'nisn' => '0077654321',
        ]);

        $admin = $this->admin();

        foreach (['Andi Saputra', 'andi@example.com', '24001', '0077654321'] as $term) {
            $response = $this->actingAs($admin)
                ->get(route('admin.students.index', ['q' => $term]));

            $response->assertOk();
            $response->assertSee('value="'.$term.'"', false);

            if ($term === '0077654321') {
                $response->assertSee('Bunga Lestari');
                $response->assertDontSee('Andi Saputra');
            } else {
                $response->assertSee('Andi Saputra');
                $response->assertDontSee('Bunga Lestari');
            }
        }
    }

    public function test_students_can_be_filtered_by_class(): void
    {
        $firstClass = $this->createClass('X TKJ 1');
        $secondClass = $this->createClass('XI TKJ 2');

        $this->createStudent('Andi Saputra', 'andi@example.com', ['class_id' => $firstClass->id]);
        $this->createStudent('Bunga Lestari', 'bunga@example.com', ['class_id' => $secondClass->id]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.students.index', ['class_id' => $firstClass->id]));

        $response->assertOk();
        $response->assertViewHas('classId', $firstClass->id);
        $response->assertSee('value="'.$firstClass->id.'" selected', false);
        $response->assertSee('Andi Saputra');
        $response->assertDontSee('Bunga Lestari');
    }

    public function test_student_index_shows_five_per_page_by_default_and_the_limit_can_be_changed_in_steps_of_five(): void
    {
        foreach (range(1, 23) as $number) {
            $this->createStudent("Siswa {$number}", "siswa{$number}@example.com");
        }

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.students.index'))
            ->assertOk()
            ->assertViewHas('students', fn ($students) => $students->count() === 5 && $students->lastPage() === 5)
            ->assertSee('Menampilkan 1–5 dari 23 siswa');

        $this->actingAs($admin)->get(route('admin.students.index', ['per_page' => 15, 'page' => 2]))
            ->assertViewHas('students', fn ($students) => $students->count() === 8 && $students->lastPage() === 2)
            ->assertSee('Menampilkan 16–23 dari 23 siswa')
            ->assertSee('per_page=15', false);

        foreach ([7, 0, 500, 'abc'] as $invalid) {
            $this->actingAs($admin)->get(route('admin.students.index', ['per_page' => $invalid]))
                ->assertViewHas('perPage', 5);
        }
    }

    public function test_teacher_index_page_size_can_be_changed_and_survives_a_search(): void
    {
        foreach (range(1, 12) as $number) {
            $this->createTeacher("Guru {$number}", "guru{$number}@example.com", ['nip' => "1980{$number}"]);
        }

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.teachers.index'))
            ->assertViewHas('teachers', fn ($teachers) => $teachers->count() === 5 && $teachers->lastPage() === 3)
            ->assertSee('Menampilkan 1–5 dari 12 guru');

        $this->actingAs($admin)->get(route('admin.teachers.index', ['per_page' => 10, 'q' => 'Guru']))
            ->assertViewHas('teachers', fn ($teachers) => $teachers->count() === 10 && $teachers->lastPage() === 2)
            ->assertSee('<input type="hidden" name="q" value="Guru">', false)
            ->assertSee('<input type="hidden" name="per_page" value="10">', false);
    }

    public function test_student_index_shows_an_empty_state_when_no_students_exist(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.students.index'));

        $response->assertOk();
        $response->assertSee('Belum ada data siswa.');
        $response->assertSee(route('admin.students.create'), false);
    }

    public function test_student_index_shows_a_search_empty_state_when_nothing_matches(): void
    {
        $this->createStudent('Andi Saputra', 'andi@example.com');

        $response = $this->actingAs($this->admin())
            ->get(route('admin.students.index', ['q' => 'TidakAda']));

        $response->assertOk();
        $response->assertSee('Siswa tidak ditemukan.');
        $response->assertSee('Tampilkan Semua');
    }

    public function test_teacher_index_displays_real_counts_and_teacher_details(): void
    {
        $active = $this->createTeacher('Budi Santoso', 'budi@example.com', [
            'nip' => '198001012005011001',
            'phone' => '081234567890',
        ]);

        $this->createTeacher('Siti Aminah', 'siti@example.com', active: false);

        $response = $this->actingAs($this->admin())->get(route('admin.teachers.index'));

        $response->assertOk();
        $response->assertViewHas('totalTeachers', 2);
        $response->assertViewHas('activeTeachers', 1);
        $response->assertViewHas('teachersWithNip', 1);

        $response->assertSee('Data Guru');
        $response->assertSee('2 Guru');
        $response->assertSeeInOrder(['Total Guru', '2']);
        $response->assertSeeInOrder(['Guru Aktif', '1']);
        $response->assertSee('Budi Santoso');
        $response->assertSee('BS');
        $response->assertSee('budi@example.com');
        $response->assertSee('198001012005011001');
        $response->assertSee('081234567890');
        $response->assertSee('Aktif');
        $response->assertSee('Nonaktif');
        $response->assertSee(route('admin.teachers.show', $active), false);
        $response->assertSee(route('admin.teachers.edit', $active), false);
        $response->assertSee("confirm('Yakin ingin menghapus data guru ini?')", false);
    }

    public function test_teachers_can_be_searched_by_name_email_and_nip(): void
    {
        $this->createTeacher('Budi Santoso', 'budi@example.com', ['nip' => '198001012005011001']);
        $this->createTeacher('Siti Aminah', 'siti@example.com', ['nip' => '199002022010012002']);

        $admin = $this->admin();

        foreach (['Budi Santoso', 'siti@example.com', '198001012005011001'] as $term) {
            $response = $this->actingAs($admin)
                ->get(route('admin.teachers.index', ['q' => $term]));

            $response->assertOk();
            $response->assertSee('value="'.$term.'"', false);

            if ($term === 'siti@example.com') {
                $response->assertSee('Siti Aminah');
                $response->assertDontSee('Budi Santoso');
            } else {
                $response->assertSee('Budi Santoso');
                $response->assertDontSee('Siti Aminah');
            }
        }
    }

    public function test_teacher_index_shows_a_search_empty_state_when_nothing_matches(): void
    {
        $this->createTeacher('Budi Santoso', 'budi@example.com');

        $response = $this->actingAs($this->admin())
            ->get(route('admin.teachers.index', ['q' => 'TidakAda']));

        $response->assertOk();
        $response->assertSee('Guru tidak ditemukan.');
        $response->assertSee('Tampilkan Semua');
    }

    public function test_class_index_displays_real_counts_and_student_totals(): void
    {
        $class = $this->createClass('X TKJ 1', '10');
        $this->createClass('XI TKJ 2', '11');

        $this->createStudent('Andi Saputra', 'andi@example.com', ['class_id' => $class->id]);
        $this->createStudent('Bunga Lestari', 'bunga@example.com', ['class_id' => $class->id]);

        $response = $this->actingAs($this->admin())->get(route('admin.classes.index'));

        $response->assertOk();
        $response->assertViewHas('totalClasses', 2);
        $response->assertViewHas('totalStudents', 2);
        $response->assertViewHas('classesWithStudents', 1);

        $response->assertSee('Data Kelas');
        $response->assertSee('2 Kelas');
        $response->assertSeeInOrder(['Total Kelas', '2']);
        $response->assertSee('X TKJ 1');
        $response->assertSee('XI TKJ 2');
        $response->assertSee('2 Siswa');
        $response->assertSee('Kode: '.$class->code);
        $response->assertSee(route('admin.classes.edit', $class), false);
        $response->assertSee("confirm('Yakin ingin menghapus kelas ini?')", false);
    }

    public function test_classes_can_be_searched_by_name_or_code(): void
    {
        $first = $this->createClass('X TKJ 1');
        $second = $this->createClass('XI TKJ 2');

        $admin = $this->admin();

        $byName = $this->actingAs($admin)->get(route('admin.classes.index', ['q' => 'X TKJ 1']));
        $byName->assertOk();
        $byName->assertSee('X TKJ 1');
        $byName->assertDontSee('XI TKJ 2');

        $byCode = $this->actingAs($admin)->get(route('admin.classes.index', ['q' => $second->code]));
        $byCode->assertOk();
        $byCode->assertSee('XI TKJ 2');
        $byCode->assertDontSee('X TKJ 1');
        $byCode->assertDontSee($first->code);
    }

    public function test_classes_can_be_filtered_by_level_and_academic_year(): void
    {
        $firstYear = $this->createYear('2025/2026');
        $secondYear = $this->createYear('2026/2027');

        $firstClass = $this->createClass('X TKJ 1', '10', $firstYear);
        $secondClass = $this->createClass('XI TKJ 2', '11', $secondYear);

        $admin = $this->admin();

        $byLevel = $this->actingAs($admin)
            ->get(route('admin.classes.index', ['level' => '10']));

        $byLevel->assertOk();
        $byLevel->assertSee('X TKJ 1');
        $byLevel->assertDontSee('XI TKJ 2');

        $byYear = $this->actingAs($admin)
            ->get(route('admin.classes.index', ['academic_year_id' => $secondYear->id]));

        $byYear->assertOk();
        $byYear->assertViewHas('academicYearId', $secondYear->id);
        $byYear->assertSee('XI TKJ 2');
        $byYear->assertDontSee('X TKJ 1');
    }

    public function test_class_index_shows_an_empty_state_when_no_classes_exist(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.classes.index'));

        $response->assertOk();
        $response->assertSee('Belum ada data kelas.');
        $response->assertSee(route('admin.classes.create'), false);
    }

    public function test_user_initials_are_built_from_the_first_two_name_words(): void
    {
        $this->assertSame('AS', User::factory()->make(['name' => 'Andi Saputra'])->initials);
        $this->assertSame('B', User::factory()->make(['name' => 'Budi'])->initials);
        $this->assertSame('?', User::factory()->make(['name' => '   '])->initials);
    }

    private function createYear(string $name): AcademicYear
    {
        return AcademicYear::create([
            'name' => $name,
            'start_date' => '2025-07-14',
            'end_date' => '2026-06-30',
            'is_active' => true,
        ]);
    }

    private function createClass(string $name, ?string $level = null, ?AcademicYear $academicYear = null): Classes
    {
        $academicYear ??= AcademicYear::firstOrCreate(
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
            'level' => $level,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createStudent(string $name, string $email, array $attributes = []): StudentProfile
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'role' => 'student',
            'is_active' => true,
        ]);

        return StudentProfile::create([
            'user_id' => $user->id,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTeacher(string $name, string $email, array $attributes = [], bool $active = true): TeacherProfile
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
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
