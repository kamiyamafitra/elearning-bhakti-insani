<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PasswordResetController extends Controller
{
    public function student(StudentProfile $student): RedirectResponse
    {
        if (blank($student->nis)) {
            return back()->with('error', 'Password tidak dapat direset karena siswa belum memiliki NIS. Lengkapi NIS terlebih dahulu.');
        }

        $this->resetPassword($student->user, $student->nis);

        return back()->with('success', "Password {$student->user->name} direset menjadi NIS. Siswa wajib membuat password baru saat masuk.");
    }

    public function teacher(TeacherProfile $teacher): RedirectResponse
    {
        if (blank($teacher->nip)) {
            return back()->with('error', 'Password tidak dapat direset karena guru belum memiliki NIP. Lengkapi NIP terlebih dahulu.');
        }

        $this->resetPassword($teacher->user, $teacher->nip);

        return back()->with('success', "Password {$teacher->user->name} direset menjadi NIP. Guru wajib membuat password baru saat masuk.");
    }

    public function classStudents(Classes $class): RedirectResponse
    {
        $students = $class->students()->with('user')->get();
        [$studentsWithNis, $studentsWithoutNis] = $students->partition(fn (StudentProfile $student) => filled($student->nis));

        if ($studentsWithNis->isEmpty()) {
            return back()->with('error', 'Tidak ada siswa dengan NIS di kelas ini yang dapat direset.');
        }

        set_time_limit(300);

        ActivityLog::withoutLogging(fn () => DB::transaction(function () use ($studentsWithNis): void {
            foreach ($studentsWithNis as $student) {
                $this->resetPassword($student->user, $student->nis);
            }
        }));

        ActivityLog::record(ActivityLog::ACTION_BULK, "mereset password {$studentsWithNis->count()} siswa kelas {$class->name}", $class, ['count' => $studentsWithNis->count()]);

        $message = "Password {$studentsWithNis->count()} siswa {$class->name} direset menjadi NIS masing-masing.";

        if ($studentsWithoutNis->isNotEmpty()) {
            $message .= " {$studentsWithoutNis->count()} siswa dilewati karena belum memiliki NIS.";
        }

        return back()->with('success', $message);
    }

    private function resetPassword(User $user, string $newPassword): void
    {
        $user->update([
            'password' => Hash::make($newPassword),
            'must_change_password' => true,
        ]);
    }
}
