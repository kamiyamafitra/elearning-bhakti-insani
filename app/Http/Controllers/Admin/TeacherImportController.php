<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ImportsSpreadsheetRows;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TeacherImportController extends Controller
{
    use ImportsSpreadsheetRows;

    public const EMAIL_DOMAIN = 'guru.smkbhaktiinsani.sch.id';

    /**
     * @var list<string>
     */
    private const COLUMNS = ['nama', 'nip', 'email', 'telepon', 'alamat'];

    /**
     * @var array<string, string>
     */
    private const COLUMN_ALIASES = [
        'nama_lengkap' => 'nama',
        'nama_guru' => 'nama',
        'no_hp' => 'telepon',
        'no_telepon' => 'telepon',
    ];

    public function create(): View
    {
        return view('admin.teachers.import', [
            'columns' => self::COLUMNS,
            'emailDomain' => self::EMAIL_DOMAIN,
        ]);
    }

    public function template(): BinaryFileResponse
    {
        return $this->downloadImportTemplate('template-impor-guru.xlsx', self::COLUMNS, [
            ['Budi Santoso', '198001012005011001', '', '081234567890', 'Jl. Pajajaran No. 10, Bogor'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rows = $this->readImportRows($request, ['nama', 'nip'], self::COLUMN_ALIASES, ['nip']);

        if ($rows instanceof RedirectResponse) {
            return $rows;
        }

        $prepared = array_map(fn (array $row) => $this->prepareTeacher($row), $rows);
        $teachers = array_column($prepared, 0);
        $existing = [
            'nip' => $this->existingValues(TeacherProfile::query(), 'nip', array_column($teachers, 'nip')),
            'email' => $this->existingValues(User::query(), 'email', array_column($teachers, 'email')),
        ];
        $errorsByRow = [];
        $seen = ['nip' => [], 'email' => []];

        foreach ($prepared as $rowNumber => [$teacher, $rowErrors]) {
            foreach (['nip' => 'NIP', 'email' => 'Email'] as $field => $label) {
                $firstRow = $this->findDuplicateRow($seen[$field], $teacher[$field], $rowNumber);

                if ($firstRow !== null) {
                    $rowErrors[] = "{$label} {$teacher[$field]} sama dengan baris {$firstRow}.";
                }

                if (isset($existing[$field][Str::lower($teacher[$field])])) {
                    $rowErrors[] = $field === 'email'
                        ? "Email {$teacher['email']} sudah digunakan akun lain."
                        : "NIP {$teacher['nip']} sudah terdaftar.";
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
        $passwords = $this->hashInitialPasswords(array_column($teachers, 'nip'));

        ActivityLog::withoutLogging(fn () => DB::transaction(function () use ($teachers, $passwords): void {
            foreach ($teachers as $index => $teacher) {
                $user = User::create([
                    'name' => $teacher['name'],
                    'email' => $teacher['email'],
                    'password' => $passwords[$index],
                    'must_change_password' => true,
                    'role' => 'teacher',
                    'is_active' => true,
                ]);

                TeacherProfile::create([
                    'user_id' => $user->id,
                    'nip' => $teacher['nip'],
                    'phone' => $teacher['phone'],
                    'address' => $teacher['address'],
                ]);
            }
        }));

        ActivityLog::record(ActivityLog::ACTION_BULK, 'mengimpor '.count($teachers).' guru dari file', properties: ['count' => count($teachers), 'nip' => array_column($teachers, 'nip')]);

        return redirect()->route('admin.teachers.index')
            ->with('success', count($teachers).' guru berhasil diimpor. Password awal setiap guru adalah NIP masing-masing.');
    }

    /**
     * Map one spreadsheet row to user and profile attributes and collect its validation errors.
     *
     * @param  array<string, string>  $row
     * @return array{0: array{name: string, email: string, nip: string, phone: ?string, address: ?string}, 1: list<string>}
     */
    private function prepareTeacher(array $row): array
    {
        $nip = $row['nip'] ?? '';

        $teacher = [
            'name' => $row['nama'] ?? '',
            'email' => Str::lower(($row['email'] ?? '') ?: ($nip !== '' ? "{$nip}@".self::EMAIL_DOMAIN : '')),
            'nip' => $nip,
            'phone' => ($row['telepon'] ?? '') ?: null,
            'address' => ($row['alamat'] ?? '') ?: null,
        ];

        $validator = validator($teacher, [
            'name' => ['required', 'string', 'max:255'],
            'nip' => ['required', 'alpha_dash', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'nip.required' => 'NIP wajib diisi.',
            'nip.alpha_dash' => 'NIP hanya boleh berisi huruf, angka, dan tanda hubung.',
            'email.email' => "Email {$teacher['email']} tidak valid.",
        ], [
            'name' => 'nama',
            'phone' => 'telepon',
        ]);

        return [$teacher, $validator->errors()->all()];
    }
}
