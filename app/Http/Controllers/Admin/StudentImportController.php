<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ImportsSpreadsheetRows;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentImportController extends Controller
{
    use ImportsSpreadsheetRows;

    public const EMAIL_DOMAIN = 'siswa.smkbhaktiinsani.sch.id';

    /**
     * @var list<string>
     */
    private const COLUMNS = [
        'nama',
        'nis',
        'nisn',
        'kelas',
        'jenis_kelamin',
        'tanggal_lahir',
        'email',
        'telepon',
        'alamat',
        'nama_orang_tua',
        'telepon_orang_tua',
    ];

    /**
     * @var array<string, string>
     */
    private const COLUMN_ALIASES = [
        'nama_lengkap' => 'nama',
        'nama_siswa' => 'nama',
        'jk' => 'jenis_kelamin',
        'l_p' => 'jenis_kelamin',
        'no_hp' => 'telepon',
        'no_telepon' => 'telepon',
        'nama_wali' => 'nama_orang_tua',
        'telepon_wali' => 'telepon_orang_tua',
    ];

    public function create(): View
    {
        return view('admin.students.import', [
            'columns' => self::COLUMNS,
            'emailDomain' => self::EMAIL_DOMAIN,
        ]);
    }

    public function template(): BinaryFileResponse
    {
        return $this->downloadImportTemplate('template-impor-siswa.xlsx', self::COLUMNS, [
            ['Andi Saputra', '24001', '0071234567', 'X-TKJ-1', 'L', '2008-01-13', '', '081234567890', 'Jl. Raya Bogor No. 1', 'Budi Saputra', '081298765432'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rows = $this->readImportRows($request, ['nama', 'nis'], self::COLUMN_ALIASES, ['nis', 'nisn']);

        if ($rows instanceof RedirectResponse) {
            return $rows;
        }

        $classes = Classes::with('academicYear:id,is_active')->get(['id', 'name', 'code', 'academic_year_id']);
        $prepared = array_map(fn (array $row) => $this->prepareStudent($row, $classes), $rows);
        $students = array_column($prepared, 0);
        $existing = [
            'nis' => $this->existingValues(StudentProfile::query(), 'nis', array_column($students, 'nis')),
            'nisn' => $this->existingValues(StudentProfile::query(), 'nisn', array_column($students, 'nisn')),
            'email' => $this->existingValues(User::query(), 'email', array_column($students, 'email')),
        ];
        $errorsByRow = [];
        $seen = ['nis' => [], 'nisn' => [], 'email' => []];

        foreach ($prepared as $rowNumber => [$student, $rowErrors]) {
            foreach (['nis' => 'NIS', 'nisn' => 'NISN', 'email' => 'Email'] as $field => $label) {
                $firstRow = $this->findDuplicateRow($seen[$field], $student[$field], $rowNumber);

                if ($firstRow !== null) {
                    $rowErrors[] = "{$label} {$student[$field]} sama dengan baris {$firstRow}.";
                }

                if (isset($existing[$field][Str::lower((string) $student[$field])])) {
                    $rowErrors[] = $field === 'email'
                        ? "Email {$student['email']} sudah digunakan akun lain."
                        : "{$label} {$student[$field]} sudah terdaftar.";
                }
            }

            if ($rowErrors !== []) {
                $errorsByRow[$rowNumber] = $rowErrors;
            }
        }

        if ($errorsByRow !== []) {
            return $this->redirectWithImportErrors($errorsByRow);
        }

        set_time_limit(300);
        $passwords = $this->hashInitialPasswords(array_column($students, 'nis'));

        ActivityLog::withoutLogging(fn () => DB::transaction(function () use ($students, $passwords): void {
            foreach ($students as $index => $student) {
                $user = User::create([
                    'name' => $student['name'],
                    'email' => $student['email'],
                    'password' => $passwords[$index],
                    'must_change_password' => true,
                    'role' => 'student',
                    'is_active' => true,
                ]);

                StudentProfile::create([
                    'user_id' => $user->id,
                    ...Arr::except($student, ['name', 'email']),
                ]);
            }
        }));

        ActivityLog::record(ActivityLog::ACTION_BULK, 'mengimpor '.count($students).' siswa dari file', properties: ['count' => count($students), 'nis' => array_column($students, 'nis')]);

        return redirect()->route('admin.students.index')
            ->with('success', count($students).' siswa berhasil diimpor. Password awal setiap siswa adalah NIS masing-masing.');
    }

    /**
     * Map one spreadsheet row to user and profile attributes and collect its validation errors.
     *
     * @param  array<string, string>  $row
     * @param  Collection<int, Classes>  $classes
     * @return array{0: array{name: string, email: string, nis: string, nisn: ?string, class_id: ?int, gender: ?string, birth_date: ?string, phone: ?string, address: ?string, parent_name: ?string, parent_phone: ?string}, 1: list<string>}
     */
    private function prepareStudent(array $row, Collection $classes): array
    {
        $nis = $row['nis'] ?? '';
        $className = $row['kelas'] ?? '';
        $gender = $row['jenis_kelamin'] ?? '';
        $birthDate = $row['tanggal_lahir'] ?? '';

        $student = [
            'name' => $row['nama'] ?? '',
            'email' => Str::lower(($row['email'] ?? '') ?: ($nis !== '' ? "{$nis}@".self::EMAIL_DOMAIN : '')),
            'nis' => $nis,
            'nisn' => ($row['nisn'] ?? '') ?: null,
            'class_id' => $className !== '' ? $this->findClassId($classes, $className) : null,
            'gender' => $gender !== '' ? $this->normalizeGender($gender) : null,
            'birth_date' => $birthDate !== '' ? $this->parseImportDate($birthDate) : null,
            'phone' => ($row['telepon'] ?? '') ?: null,
            'address' => ($row['alamat'] ?? '') ?: null,
            'parent_name' => ($row['nama_orang_tua'] ?? '') ?: null,
            'parent_phone' => ($row['telepon_orang_tua'] ?? '') ?: null,
        ];

        $validator = validator($student, [
            'name' => ['required', 'string', 'max:255'],
            'nis' => ['required', 'alpha_dash', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'nisn' => ['nullable', 'digits:10'],
            'phone' => ['nullable', 'string', 'max:20'],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'parent_phone' => ['nullable', 'string', 'max:20'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'nis.required' => 'NIS wajib diisi.',
            'nis.alpha_dash' => 'NIS hanya boleh berisi huruf, angka, dan tanda hubung.',
            'nisn.digits' => 'NISN '.($student['nisn'] ?? '').' harus 10 digit. Jika angka 0 di depan hilang, ubah format selnya menjadi Text.',
            'email.email' => "Email {$student['email']} tidak valid.",
        ], [
            'name' => 'nama',
            'phone' => 'telepon',
            'parent_name' => 'nama orang tua',
            'parent_phone' => 'telepon orang tua',
        ]);

        $errors = $validator->errors()->all();

        if ($className !== '' && $student['class_id'] === null) {
            $errors[] = "Kelas {$className} tidak ditemukan. Gunakan kode atau nama kelas yang sudah terdaftar.";
        }

        if ($gender !== '' && $student['gender'] === null) {
            $errors[] = 'Jenis kelamin harus L atau P.';
        }

        if ($birthDate !== '' && $student['birth_date'] === null) {
            $errors[] = "Tanggal lahir {$birthDate} tidak valid. Gunakan format 2008-01-13 atau 13/01/2008.";
        }

        return [$student, $errors];
    }

    /**
     * Match a class by its code, then by name, preferring classes in the active academic year.
     *
     * @param  Collection<int, Classes>  $classes
     */
    private function findClassId(Collection $classes, string $value): ?int
    {
        $value = Str::lower($value);

        $class = $classes->first(fn (Classes $class) => Str::lower($class->code) === $value);

        if ($class) {
            return $class->id;
        }

        $matchingByName = $classes->filter(fn (Classes $class) => Str::lower($class->name) === $value);

        if ($matchingByName->count() > 1) {
            $matchingByName = $matchingByName->filter(fn (Classes $class) => $class->academicYear?->is_active);
        }

        return $matchingByName->count() === 1 ? $matchingByName->first()->id : null;
    }

    private function normalizeGender(string $value): ?string
    {
        return match (Str::lower($value)) {
            'l', 'laki-laki', 'laki laki', 'pria' => 'L',
            'p', 'perempuan', 'wanita' => 'P',
            default => null,
        };
    }
}
