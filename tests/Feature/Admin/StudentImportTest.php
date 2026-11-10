<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\StudentImportController;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\Feature\Concerns\ReadsCredentialSlips;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use CreatesAcademicData;
    use ReadsCredentialSlips;
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

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_csv_import_creates_students_with_generated_email_and_random_temporary_password(): void
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
        $passwords = $this->slipPasswords();
        $this->assertTrue(Hash::check($passwords['24001'], $andi->password));
        $this->assertFalse(Hash::check('24001', $andi->password), 'The NIS must not be the password.');
        $this->assertTrue($andi->must_change_password);
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

    public function test_school_export_xls_imports_students_and_creates_missing_classes(): void
    {
        $existing = $this->makeClass('XII Pemrograman Perangkat Lunak dan Gim 1');
        $header = '<tr><th rowspan="2">No.</th><th rowspan="2">Nama Siswa</th><th rowspan="2">Kelas</th><th rowspan="2">NISN</th><th rowspan="2">NIS</th>'
            .'<th rowspan="2">Jenis Kelamin</th><th rowspan="2">No.HP</th><th rowspan="2">No.HP2</th><th rowspan="2">Tanggal Lahir</th><th rowspan="2">Alamat</th><th rowspan="2">Hobby</th></tr><tr></tr>';
        $row = fn (array $cells) => '<tr>'.implode('', array_map(fn (string $cell) => "<td>{$cell}</td>", $cells)).'</tr>';
        $html = '<!DOCTYPE html><html><head><title>Export Data Siswa</title></head><body><center><h3>Export Data Siswa Tahun Ajaran 2026/2027</h3></center><table border="1">'
            .$header
            .$row(['1', 'AFDIL ALFARIZI HIDAYAT', '12-Pemrograman Perangkat Lunak dan GIM (PPLG)-1', '0093222787', '2425.10001', '', '083162706670', '083110640474', '2009-07-08', 'KP. BALUBURSARI', 'Futsal'])
            .$row(['2', 'MUH. AL-FATIH', '10-Teknik Jaringan Komputer dan Telekomunikasi (TJKT)-1', '', '2526.10002', '', '', '', '', '', ''])
            .$row(['3', 'Siti Nur Aisyah', '10-Teknik Jaringan Komputer dan Telekomunikasi (TJKT)-1', '', '2526.10003', '', '', '', '2010-01-02', '', ''])
            .'</table></body></html>';

        $response = $this->actingAs($this->admin())->post(route('admin.students.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('exportdatasiswa_2026_2027.xls', $html),
        ]);

        $response->assertRedirect(route('admin.students.index'));
        $response->assertSessionHas('success', fn (string $message) => str_contains($message, '3 siswa berhasil diimpor.') && str_contains($message, '1 kelas baru ikut dibuat: X Teknik Jaringan Komputer dan Telekomunikasi 1.'));

        $afdil = StudentProfile::where('nis', '2425.10001')->firstOrFail();
        $this->assertSame('Afdil Alfarizi Hidayat', $afdil->user->name);
        $this->assertSame('2425.10001@'.StudentImportController::EMAIL_DOMAIN, $afdil->user->email);
        $this->assertTrue(Hash::check($this->slipPasswords()['2425.10001'], $afdil->user->password));
        $this->assertSame('0093222787', $afdil->nisn);
        $this->assertSame($existing->id, $afdil->class_id);
        $this->assertSame('083162706670', $afdil->phone);
        $this->assertSame('083110640474', $afdil->parent_phone);
        $this->assertSame('2009-07-08', $afdil->birth_date->format('Y-m-d'));

        $newClass = Classes::where('name', 'X Teknik Jaringan Komputer dan Telekomunikasi 1')->firstOrFail();
        $this->assertSame(['X-TJKT-1', 'X', $existing->academic_year_id], [$newClass->code, $newClass->level, $newClass->academic_year_id]);
        $this->assertSame(['Muh. Al-Fatih', 'Siti Nur Aisyah'], $newClass->students()->with('user')->orderBy('nis')->get()->pluck('user.name')->all());
    }

    public function test_school_export_classes_are_not_created_without_an_active_year(): void
    {
        $html = '<table><tr><th>Nama Siswa</th><th>NIS</th><th>Kelas</th></tr>'
            .'<tr><td>Andi</td><td>2526.10009</td><td>11-Pemasaran (PM)-2</td></tr></table>';

        $this->actingAs($this->admin())->post(route('admin.students.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('siswa.xls', $html),
        ])->assertSessionHas('import_errors', ['Baris 2: Kelas 11-Pemasaran (PM)-2 tidak dapat dibuat karena belum ada tahun ajaran aktif.']);

        $this->assertSame(0, Classes::count());
        $this->assertSame(0, StudentProfile::count());
    }

    public function test_old_binary_xls_files_are_rejected(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.students.import'))
            ->post(route('admin.students.import.store'), [
                'file' => UploadedFile::fake()->createWithContent('siswa.xls', "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 504)),
            ])
            ->assertRedirect(route('admin.students.import'));

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
