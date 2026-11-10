<?php

namespace Tests\Feature\Admin;

use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\Feature\Concerns\ReadsCredentialSlips;
use Tests\TestCase;

class TeacherImportTest extends TestCase
{
    use CreatesAcademicData;
    use ReadsCredentialSlips;
    use RefreshDatabase;

    public function test_guests_are_redirected_and_non_admins_are_forbidden(): void
    {
        $this->get(route('admin.teachers.import'))->assertRedirect(route('login'));

        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);

        $this->actingAs($student)->get(route('admin.teachers.import'))->assertForbidden();
    }

    public function test_import_page_and_template_are_available(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.teachers.index'))
            ->assertSee(route('admin.teachers.import'), false);

        $this->actingAs($admin)->get(route('admin.teachers.import'))
            ->assertOk()
            ->assertSee('Impor Data Guru')
            ->assertSee(route('admin.teachers.import.template'), false);

        $this->actingAs($admin)->get(route('admin.teachers.import.template'))
            ->assertOk()
            ->assertDownload('template-impor-guru.xlsx');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_csv_import_creates_teachers_with_generated_email_and_random_temporary_password(): void
    {
        $csv = implode("\n", [
            'Nama Lengkap,NIP,Email,No HP,Alamat',
            'Budi Santoso,198001012005011001,,0812,Bogor',
            'Sari Wulandari,198502022010012002,sari@example.com,,',
        ]);

        $response = $this->actingAs($this->admin())->post(route('admin.teachers.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('guru.csv', $csv),
        ]);

        $response->assertRedirect(route('admin.teachers.index'));
        $response->assertSessionHas('success', fn (string $message) => str_starts_with($message, '2 guru berhasil diimpor.'));

        $budi = User::where('email', '198001012005011001@guru.smkbhaktiinsani.sch.id')->firstOrFail();
        $this->assertSame('teacher', $budi->role);
        $this->assertTrue(Hash::check($this->slipPasswords()['198001012005011001'], $budi->password));
        $this->assertFalse(Hash::check('198001012005011001', $budi->password), 'The NIP must not be the password.');
        $this->assertDatabaseHas('teacher_profiles', [
            'user_id' => $budi->id,
            'nip' => '198001012005011001',
            'phone' => '0812',
            'address' => 'Bogor',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'sari@example.com', 'role' => 'teacher']);
    }

    public function test_invalid_rows_cancel_the_import(): void
    {
        $this->makeTeacher('Guru Lama', '197001012000011001');

        $csv = implode("\n", [
            'nama,nip',
            'Guru Baru,198001012005011001',
            'Guru Duplikat,197001012000011001',
            'Tanpa NIP,',
        ]);

        $response = $this->actingAs($this->admin())->post(route('admin.teachers.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('guru.csv', $csv),
        ]);

        $response->assertSessionHas('import_errors', [
            'Baris 3: NIP 197001012000011001 sudah terdaftar.',
            'Baris 4: NIP wajib diisi.',
        ]);
        $this->assertSame(1, TeacherProfile::count());
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
