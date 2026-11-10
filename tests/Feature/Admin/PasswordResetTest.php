<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\CredentialSlip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

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

    public function test_student_password_is_reset_to_a_random_temporary_password_shown_once(): void
    {
        $student = $this->makeStudent($this->makeClass(), 'Andi Saputra', '24001');

        $response = $this->actingAs($this->admin())
            ->from(route('admin.students.show', $student->studentProfile))
            ->post(route('admin.students.reset-password', $student->studentProfile));

        $response->assertRedirect(route('admin.students.show', $student->studentProfile));
        $message = session('success');
        $this->assertMatchesRegularExpression('/^Password sementara Andi Saputra: ([a-z2-9]{8}) \(masuk dengan NIS 24001\)/', $message);
        preg_match('/: ([a-z2-9]{8}) /', $message, $match);

        $student->refresh();
        $this->assertTrue(Hash::check($match[1], $student->password));
        $this->assertFalse(Hash::check('24001', $student->password), 'The NIS must no longer work as a password.');
        $this->assertTrue($student->must_change_password);
    }

    public function test_teacher_password_is_reset_to_a_random_temporary_password(): void
    {
        $teacher = $this->makeTeacher('Budi Santoso', '198001012005011001');

        $this->actingAs($this->admin())
            ->post(route('admin.teachers.reset-password', $teacher->teacherProfile))
            ->assertSessionHas('success');

        preg_match('/: ([a-z2-9]{8}) /', session('success'), $match);

        $teacher->refresh();
        $this->assertTrue(Hash::check($match[1], $teacher->password));
        $this->assertFalse(Hash::check('198001012005011001', $teacher->password));
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

        $admin = $this->admin();
        $response = $this->actingAs($admin)->post(route('admin.classes.reset-passwords', $class));

        $response->assertSessionHas('success', 'Password 2 siswa X TKJ 1 direset menjadi password sementara acak. Unduh slip password untuk dibagikan. 1 siswa dilewati karena belum memiliki NIS.');
        $response->assertSessionHas('credential_slip.count', 2);

        $this->assertFalse(Hash::check('24001', $first->fresh()->password));
        $this->assertTrue($first->fresh()->must_change_password);
        $this->assertTrue($second->fresh()->must_change_password);
        $this->assertFalse($withoutNis->fresh()->must_change_password);
        $this->assertFalse($otherClassStudent->fresh()->must_change_password);

        // The page the admin lands on offers the slip, which lists each student with a password that really works
        // and can only be downloaded once.
        $slipId = session('credential_slip.id');
        $this->actingAs($admin)->get(route('admin.classes.show', $class))
            ->assertSee('Unduh Slip Password')
            ->assertSee(route('admin.credential-slips.download', $slipId), false);

        $download = $this->actingAs($admin)->get(route('admin.credential-slips.download', $slipId));
        $download->assertOk();
        $download->assertDownload('slip-password-reset-kelas-x-tkj-1.xlsx');

        $rows = $this->readRows($download->getFile()->getPathname());
        $this->assertSame(['No', 'Nama', 'NIS', 'Kelas', 'Password Sementara', 'Tanda Terima'], $rows[2]);
        $this->assertSame([1, 'Andi', '24001', 'X TKJ 1'], array_slice($rows[3], 0, 4));
        $this->assertTrue(Hash::check($rows[3][4], $first->fresh()->password));
        $this->assertTrue(Hash::check($rows[4][4], $second->fresh()->password));

        $this->actingAs($admin)->get(route('admin.credential-slips.download', $slipId))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error');
        Storage::disk('local')->assertDirectoryEmpty(CredentialSlip::DIRECTORY);
    }

    public function test_only_admins_can_download_password_slips_and_the_stored_file_is_encrypted(): void
    {
        $class = $this->makeClass('X TKJ 1');
        $this->makeStudent($class, 'Andi', '24001');
        $teacher = $this->makeTeacher();

        $this->actingAs($this->admin())->post(route('admin.classes.reset-passwords', $class));
        $slipId = session('credential_slip.id');

        $stored = Storage::disk('local')->get(CredentialSlip::DIRECTORY."/{$slipId}.slip");
        $this->assertStringNotContainsString('24001', $stored);
        $this->assertStringNotContainsString('Andi', $stored);

        $this->actingAs($teacher)->get(route('admin.credential-slips.download', $slipId))->assertForbidden();
        $this->get(route('admin.credential-slips.download', $slipId))->assertForbidden();
    }

    /**
     * @return list<list<mixed>>
     */
    private function readRows(string $path): array
    {
        $reader = new XlsxReader;
        $reader->open($path);
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }

        $reader->close();

        return $rows;
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
