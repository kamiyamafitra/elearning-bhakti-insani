<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class AccountStatusController extends Controller
{
    public function student(StudentProfile $student): RedirectResponse
    {
        return $this->toggle($student->user);
    }

    public function teacher(TeacherProfile $teacher): RedirectResponse
    {
        return $this->toggle($teacher->user);
    }

    private function toggle(User $user): RedirectResponse
    {
        $user->update(['is_active' => ! $user->is_active]);

        $status = $user->is_active ? 'diaktifkan kembali' : 'dinonaktifkan';

        return back()->with('success', "Akun {$user->name} berhasil {$status}.");
    }
}
