<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\PasswordRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasswordController extends Controller
{
    /**
     * Show the standalone password page, used when an administrator requires the user to choose a new password.
     */
    public function edit(Request $request): View
    {
        return view('auth.password', [
            'mustChangePassword' => $request->user()->must_change_password,
        ]);
    }

    /**
     * Let any signed-in user replace their password after confirming the current one.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', ...PasswordRules::rules(), 'confirmed', 'different:current_password'],
        ], [
            ...PasswordRules::messages(),
            'current_password.required' => 'Password saat ini wajib diisi.',
            'current_password.current_password' => 'Password saat ini tidak sesuai.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
            'password.different' => 'Password baru harus berbeda dari password saat ini.',
        ]);

        $user = $request->user();
        $wasRequiredToChange = $user->must_change_password;

        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        // Anyone else still signed in to this account (another device, or someone who guessed the old password)
        // is signed out; this browser stays signed in under a fresh session id.
        Auth::logoutOtherDevices($validated['password']);
        $request->session()->regenerate();

        if ($wasRequiredToChange) {
            $dashboardRoute = match ($user->role) {
                'admin' => 'admin.dashboard',
                'teacher' => 'teacher.dashboard',
                default => 'student.dashboard',
            };

            return redirect()->route($dashboardRoute)->with('success', 'Password berhasil diperbarui. Selamat datang!');
        }

        return back()->with('success', 'Password berhasil diperbarui.');
    }
}
