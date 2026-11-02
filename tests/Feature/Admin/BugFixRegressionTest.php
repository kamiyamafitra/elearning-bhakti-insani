<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\ClassMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;
use ZipArchive;

class BugFixRegressionTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    public function test_promoted_student_can_be_deleted(): void
    {
        $sourceClass = $this->makeClass('X TKJ 1', $this->makeAcademicYear('2025/2026'));
        $targetClass = $this->makeClass('XI TKJ 1', $this->makeAcademicYear('2026/2027'));
        $student = $this->makeStudent($sourceClass, 'Andi', '24001');
        $student->studentProfile->update(['class_id' => $targetClass->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.students.destroy', $student->studentProfile))
            ->assertRedirect(route('admin.students.index'));

        $this->assertModelMissing($student);
        $this->assertSame(0, ClassMembership::where('student_id', $student->id)->count());
    }

    public function test_teacher_with_teaching_data_cannot_be_deleted_and_gets_a_message(): void
    {
        $teacher = $this->makeTeacher();
        $this->makeAssignment($this->makeSubject('Jaringan', 'JAR'), $this->makeClass(), $teacher);

        $this->actingAs($this->admin())
            ->delete(route('admin.teachers.destroy', $teacher->teacherProfile))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertModelExists($teacher);
    }

    public function test_graduated_student_can_be_reactivated_and_the_change_is_logged(): void
    {
        $student = $this->makeStudent($this->makeClass(), 'Andi', '24001');
        $student->update(['is_active' => false]);

        $this->actingAs($this->admin())
            ->from(route('admin.students.show', $student->studentProfile))
            ->patch(route('admin.students.toggle-active', $student->studentProfile))
            ->assertRedirect(route('admin.students.show', $student->studentProfile));

        $this->assertTrue($student->fresh()->is_active);
        $this->assertEquals([false, true], ActivityLog::where('subject_type', User::class)->where('subject_id', $student->id)->latest('id')->first()->properties['changes']['is_active']);
    }

    public function test_only_admins_can_change_account_status(): void
    {
        $student = $this->makeStudent($this->makeClass(), 'Andi', '24001');

        $this->actingAs($this->makeTeacher())
            ->patch(route('admin.students.toggle-active', $student->studentProfile))
            ->assertForbidden();
    }

    public function test_admin_account_command_creates_and_resets_an_admin(): void
    {
        $this->artisan('app:admin-account', ['username' => 'Kepala'])
            ->expectsQuestion('Password', 'rahasia-awal')
            ->assertSuccessful();

        $admin = User::where('username', 'kepala')->sole();
        $this->assertSame('admin', $admin->role);
        $admin->update(['is_active' => false]);

        $this->artisan('app:admin-account', ['username' => 'kepala'])
            ->expectsQuestion('Password', 'rahasia-baru')
            ->assertSuccessful();

        $this->assertTrue($admin->fresh()->is_active);
        $this->assertTrue(Hash::check('rahasia-baru', $admin->fresh()->password));
    }

    public function test_admin_account_command_refuses_short_passwords_and_non_admin_usernames(): void
    {
        $this->artisan('app:admin-account', ['username' => 'kepala'])
            ->expectsQuestion('Password', 'pendek')
            ->assertFailed();

        $student = User::factory()->create(['role' => 'student', 'username' => 'andi']);

        $this->artisan('app:admin-account', ['username' => 'andi'])->assertFailed();
        $this->assertSame('student', $student->fresh()->role);
    }

    public function test_rounded_identifiers_from_excel_are_rejected(): void
    {
        $path = $this->spreadsheet([['nama', 'nip'], ['Budi Santoso', 198001012005011000]]);

        $this->actingAs($this->admin())
            ->from(route('admin.teachers.import'))
            ->post(route('admin.teachers.import.store'), ['file' => new UploadedFile($path, 'guru.xlsx', test: true)])
            ->assertRedirect(route('admin.teachers.import'))
            ->assertSessionHas('import_errors', fn (array $errors) => str_contains($errors[0], 'Baris 2: NIP sudah dibulatkan'));

        $this->assertDatabaseMissing('users', ['name' => 'Budi Santoso']);
    }

    public function test_nisn_that_lost_its_leading_zero_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.students.import'))
            ->post(route('admin.students.import.store'), [
                'file' => UploadedFile::fake()->createWithContent('siswa.csv', "nama,nis,nisn\nAndi,24001,71234567"),
            ])
            ->assertSessionHas('import_errors', fn (array $errors) => str_contains($errors[0], 'harus 10 digit'));

        $this->assertDatabaseMissing('student_profiles', ['nis' => '24001']);
    }

    public function test_imports_report_values_that_already_exist(): void
    {
        $this->makeStudent($this->makeClass(), 'Andi', '24001');

        $this->actingAs($this->admin())
            ->from(route('admin.students.import'))
            ->post(route('admin.students.import.store'), [
                'file' => UploadedFile::fake()->createWithContent('siswa.csv', "nama,nis\nAndi Lain,24001"),
            ])
            ->assertSessionHas('import_errors', fn (array $errors) => str_contains($errors[0], 'NIS 24001 sudah terdaftar.'));
    }

    public function test_imported_accounts_can_sign_in_with_their_initial_password(): void
    {
        $this->actingAs($this->admin())->post(route('admin.teachers.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('guru.csv', "nama,nip\nBudi,198001012005011001"),
        ]);
        auth()->logout();

        $this->post(route('login'), ['username' => '198001012005011001', 'password' => '198001012005011001'])
            ->assertRedirect(route('teacher.dashboard'));
    }

    public function test_import_template_formats_empty_rows_as_text(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.teachers.import.template'));

        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertMatchesRegularExpression('/<c r="B3"[^>]* s="[1-9]/', $sheet);
    }

    public function test_formula_like_text_is_exported_as_plain_text(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent($class, 'Andi', '24001');
        $student->update(['name' => '=HYPERLINK("http://evil","klik")']);

        $response = $this->actingAs($this->admin())->get(route('admin.grades.export', ['class_id' => $class->id]));

        $reader = new Reader;
        $reader->open($response->baseResponse->getFile()->getPathname());
        $cells = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = [...$cells, ...$row->toArray()];
            }

            break;
        }

        $reader->close();

        $this->assertContains('=HYPERLINK("http://evil","klik")', $cells);

        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $this->assertStringNotContainsString('<f>', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
    }

    public function test_student_moved_within_a_year_leaves_the_old_class_recap_unless_they_have_work_there(): void
    {
        $year = $this->makeAcademicYear('2025/2026');
        $oldClass = $this->makeClass('X TKJ 1', $year);
        $newClass = $this->makeClass('X TKJ 2', $year);
        $moved = $this->makeStudent($oldClass, 'Andi', '24001');
        $movedWithWork = $this->makeStudent($oldClass, 'Bunga', '24002');
        $assignment = $this->makeAssignment($this->makeSubject('Jaringan', 'JAR'), $oldClass, $this->makeTeacher());
        $this->makeSubmission($assignment, $movedWithWork, ['score' => 90]);

        $this->actingAs($this->admin())->post(route('admin.classes.assign-students.store', $newClass), [
            'student_profile_ids' => [$moved->studentProfile->id, $movedWithWork->studentProfile->id],
        ]);

        $this->assertDatabaseHas('class_memberships', ['class_id' => $newClass->id, 'student_id' => $moved->id]);
        $this->assertDatabaseMissing('class_memberships', ['class_id' => $oldClass->id, 'student_id' => $moved->id]);

        $this->actingAs($this->admin())->get(route('admin.grades.index', ['class_id' => $oldClass->id]))
            ->assertSee('Bunga')
            ->assertDontSee('Andi');
    }

    public function test_only_the_first_blocked_attempts_are_logged_and_unknown_usernames_are_masked(): void
    {
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $this->post(route('login'), ['username' => 'rahasia123', 'password' => 'salah']);
        }

        $this->assertSame(5, ActivityLog::where('action', 'login_failed')->count());
        $this->assertSame('gagal masuk dengan username "ra********"', ActivityLog::where('action', 'login_failed')->first()->description);
    }

    public function test_failed_login_logging_is_capped_per_ip_address(): void
    {
        for ($attempt = 0; $attempt < 25; $attempt++) {
            $this->post(route('login'), ['username' => "user{$attempt}", 'password' => 'salah']);
        }

        $this->assertSame(20, ActivityLog::where('action', 'login_failed')->count());
    }

    public function test_times_use_indonesian_western_time(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('+07:00', now()->format('P'));
    }

    /**
     * @param  list<list<string|int>>  $rows
     */
    private function spreadsheet(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return $path;
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }
}
