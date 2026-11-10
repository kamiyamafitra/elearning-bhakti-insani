<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Support\CredentialSlip;
use App\Support\TemporaryPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Resets give each account a random temporary password (never its NIS or NIP, which anyone could guess)
 * and force a new password at the next sign-in. The account still signs in with its NIS or NIP.
 */
class PasswordResetController extends Controller
{
    public function student(StudentProfile $student): RedirectResponse
    {
        if (blank($student->nis)) {
            return back()->with('error', 'Password tidak dapat direset karena siswa belum memiliki NIS. Lengkapi NIS terlebih dahulu.');
        }

        $password = $this->resetPassword($student->user);

        return back()->with('success', "Password sementara {$student->user->name}: {$password} (masuk dengan NIS {$student->nis}). Catat dan berikan langsung kepada siswa; password ini hanya ditampilkan sekali dan wajib diganti saat masuk.");
    }

    public function teacher(TeacherProfile $teacher): RedirectResponse
    {
        if (blank($teacher->nip)) {
            return back()->with('error', 'Password tidak dapat direset karena guru belum memiliki NIP. Lengkapi NIP terlebih dahulu.');
        }

        $password = $this->resetPassword($teacher->user);

        return back()->with('success', "Password sementara {$teacher->user->name}: {$password} (masuk dengan NIP {$teacher->nip}). Catat dan berikan langsung kepada guru; password ini hanya ditampilkan sekali dan wajib diganti saat masuk.");
    }

    public function classStudents(Classes $class): RedirectResponse
    {
        $students = $class->students()->with('user')->get()->sortBy('user.name')->values();
        [$studentsWithNis, $studentsWithoutNis] = $students->partition(fn (StudentProfile $student) => filled($student->nis));

        if ($studentsWithNis->isEmpty()) {
            return back()->with('error', 'Tidak ada siswa dengan NIS di kelas ini yang dapat direset.');
        }

        set_time_limit(300);

        $slipRows = ActivityLog::withoutLogging(fn () => DB::transaction(fn (): array => $studentsWithNis
            ->map(fn (StudentProfile $student) => [
                'name' => $student->user->name,
                'identifier' => (string) $student->nis,
                'group' => $class->name,
                'password' => $this->resetPassword($student->user),
            ])
            ->values()
            ->all()));

        ActivityLog::record(ActivityLog::ACTION_BULK, "mereset password {$studentsWithNis->count()} siswa kelas {$class->name}", $class, ['count' => $studentsWithNis->count()]);

        $message = "Password {$studentsWithNis->count()} siswa {$class->name} direset menjadi password sementara acak. Unduh slip password untuk dibagikan.";

        if ($studentsWithoutNis->isNotEmpty()) {
            $message .= " {$studentsWithoutNis->count()} siswa dilewati karena belum memiliki NIS.";
        }

        return back()
            ->with('success', $message)
            ->with('credential_slip', [
                'id' => CredentialSlip::store("Reset Kelas {$class->name}", 'NIS', $slipRows),
                'count' => count($slipRows),
            ]);
    }

    /**
     * @return string the new temporary password, to hand to the account owner
     */
    private function resetPassword(User $user): string
    {
        $password = TemporaryPassword::generate();

        $user->update([
            'password' => Hash::make($password),
            'must_change_password' => true,
        ]);

        return $password;
    }
}
