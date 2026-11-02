<?php

namespace Tests\Feature\Admin;

use App\Models\StudentProfile;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    public function test_guests_are_redirected_and_non_admins_are_forbidden(): void
    {
        $this->get(route('admin.students.import'))->assertRedirect(route('login'));

        $teacher = User::factory()->create(['role' => 'teacher', 'is_active' => true]);

        $this->actingAs($teacher)->get(route('admin.students.import'))->assertForbidden();
        $this->actingAs($teacher)->post(route('admin.students.import.store'))->assertForbidden();
    }

    public function test_import_page_shows_the_template_link_and_column_guide(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.students.import'));

        $response->assertOk();
        $response->assertSee('Impor Data Siswa');
        $response->assertSee(route('admin.students.import.template'), false);
        $response->assertSee('action="'.route('admin.students.import.store').'"', false);
        $response->assertSee('enctype="multipart/form-data"', false);
        $response->assertSee('nama_orang_tua');
    }

    public function test_student_index_links_to_the_import_page(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.students.index'))
            ->assertSee(route('admin.students.import'), false);
    }

    public function test_template_download_is_an_xlsx_file_with_the_expected_columns(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.students.import.template'));

        $response->assertOk();
        $response->assertDownload('template-impor-siswa.xlsx');

        $rows = $this->readXlsx($response->getFile()->getPathname());

        $this->assertSame(['nama', 'nis', 'nisn', 'kelas'], array_slice($rows[0], 0, 4));
        $this->assertSame('24001', $rows[1][1]);
    }

    public function test_csv_import_creates_students_with_generated_email_and_nis_password(): void
    {
        $classByCode = $this->makeClass('X TKJ 1');
        $classByName = $this->makeClass('XI RPL 2');

        $csv = implode("\n", [
            'nama,nis,nisn,kelas,jenis_kelamin,tanggal_lahir,email,telepon,alamat,nama_orang_tua,telepon_orang_tua',
            "Andi Saputra,24001,0071234567,{$classByCode->code},L,13/01/2008,,0812,Bogor,Budi,0813",
            'Bunga Lestari,24002,,xi rpl 2,Perempuan,2008-05-02,bunga@example.com,,,,',
        ]);

        $response = $this->actingAs($this->admin())->post(route('admin.students.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('siswa.csv', $csv),
        ]);

        $response->assertRedirect(route('admin.students.index'));
        $response->assertSessionHas('success', fn (string $message) => str_starts_with($message, '2 siswa berhasil diimpor.'));

        $andi = User::where('email', '24001@siswa.smkbhaktiinsani.sch.id')->firstOrFail();
        $this->assertSame('Andi Saputra', $andi->name);
        $this->assertSame('student', $andi->role);
        $this->assertTrue($andi->is_active);
        $this->assertTrue(Hash::check('24001', $andi->password));
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $andi->id,
            'nis' => '24001',
            'nisn' => '0071234567',
            'class_id' => $classByCode->id,
            'gender' => 'L',
            'phone' => '0812',
            'parent_name' => 'Budi',
        ]);
        $this->assertSame('2008-01-13', $andi->studentProfile->birth_date->format('Y-m-d'));

        $bunga = User::where('email', 'bunga@example.com')->firstOrFail();
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $bunga->id,
            'class_id' => $classByName->id,
            'gender' => 'P',
            'nisn' => null,
        ]);
    }

    public function test_semicolon_separated_csv_from_indonesian_excel_is_supported(): void
    {
        $csv = "\u{FEFF}Nama;NIS;Kelas\nCitra Dewi;24003;\n";

        $this->actingAs($this->admin())->post(route('admin.students.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('siswa.csv', $csv),
        ])->assertRedirect(route('admin.students.index'));

        $this->assertDatabaseHas('student_profiles', ['nis' => '24003', 'class_id' => null]);
        $this->assertDatabaseHas('users', ['name' => 'Citra Dewi']);
    }

    public function test_xlsx_import_handles_numeric_and_date_cells(): void
    {
        $file = $this->makeXlsx([
            ['nama', 'nis', 'tanggal_lahir'],
            ['Dedi Kurniawan', 24004, new DateTimeImmutable('2008-03-09')],
        ]);

        $this->actingAs($this->admin())->post(route('admin.students.import.store'), [
            'file' => $file,
        ])->assertRedirect(route('admin.students.index'));

        $profile = StudentProfile::where('nis', '24004')->firstOrFail();
        $this->assertSame('2008-03-09', $profile->birth_date->format('Y-m-d'));
    }

    public function test_any_invalid_row_cancels_the_whole_import_and_lists_every_error(): void
    {
        $class = $this->makeClass('X TKJ 1');
        $this->makeStudent($class, 'Siswa Lama', '23001');

        $csv = implode("\n", [
            'nama,nis,kelas,jenis_kelamin,tanggal_lahir',
            'Valid Satu,24010,,,',
            ',24011,,,',
            'Sudah Ada,23001,,,',
            'Kembar,24010,,,',
            'Kelas Salah,24012,XII ZZZ,X,31/02/2008',
        ]);

        $response = $this->actingAs($this->admin())
            ->from(route('admin.students.import'))
            ->post(route('admin.students.import.store'), [
                'file' => UploadedFile::fake()->createWithContent('siswa.csv', $csv),
            ]);

        $response->assertRedirect(route('admin.students.import'));
        $response->assertSessionHas('error');
        $response->assertSessionHas('import_errors', function (array $errors): bool {
            $expected = [
                'Baris 3: Nama wajib diisi.',
                'Baris 4: NIS 23001 sudah terdaftar.',
                'Baris 5: NIS 24010 sama dengan baris 2.',
                'Baris 6: Kelas XII ZZZ tidak ditemukan.',
                'Baris 6: Jenis kelamin harus L atau P.',
                'Baris 6: Tanggal lahir 31/02/2008 tidak valid.',
            ];

            foreach ($expected as $message) {
                if (! collect($errors)->contains(fn (string $error) => str_starts_with($error, $message))) {
                    return false;
                }
            }

            return true;
        });

        $this->assertDatabaseMissing('student_profiles', ['nis' => '24010']);
        $this->assertSame(1, StudentProfile::count());
    }

    public function test_errors_are_displayed_on_the_import_page(): void
    {
        $page = $this->actingAs($this->admin())
            ->from(route('admin.students.import'))
            ->followingRedirects()
            ->post(route('admin.students.import.store'), [
                'file' => UploadedFile::fake()->createWithContent('siswa.csv', "nama,nis\n,24020"),
            ]);

        $page->assertOk();
        $page->assertSee('Daftar kesalahan');
        $page->assertSee('Baris 2: Nama wajib diisi.');
    }

    public function test_file_without_required_columns_or_rows_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.students.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('siswa.csv', "nama,kelas\nAndi,X"),
        ])->assertSessionHas('error', fn (string $message) => str_contains($message, 'nis'));

        $this->actingAs($admin)->post(route('admin.students.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('siswa.csv', "nama,nis\n"),
        ])->assertSessionHas('error', fn (string $message) => str_contains($message, 'tidak berisi data'));

        $this->actingAs($admin)->post(route('admin.students.import.store'), [
            'file' => UploadedFile::fake()->create('siswa.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, StudentProfile::count());
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function makeXlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx-test');
        $writer = new XlsxWriter;
        $writer->openToFile($path);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return new UploadedFile($path, 'siswa.xlsx', null, null, true);
    }

    /**
     * @return list<list<mixed>>
     */
    private function readXlsx(string $path): array
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
