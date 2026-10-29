<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * The number of failed attempts allowed before the login is throttled.
     */
    private const MAX_LOGIN_ATTEMPTS = 5;

    /**
     * The number of seconds the login throttle stays active.
     */
    private const THROTTLE_DECAY_SECONDS = 60;

    /**
     * The roles that can sign in through the login form.
     *
     * @var list<string>
     */
    private const ROLES = ['student', 'teacher', 'admin'];

    public function showLoginForm(): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user && in_array($user->role, self::ROLES, true)) {
            return redirect()->route($this->dashboardRouteFor($user->role));
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $this->normalizeIdentifier($request);

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim($validated['username']);
        $password = $validated['password'];
        $throttleKey = $this->throttleKey($identifier, $request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            return $this->failedLogin(
                'Terlalu banyak percobaan masuk. Silakan coba lagi dalam '
                    .RateLimiter::availableIn($throttleKey).' detik.',
            );
        }

        $candidates = $this->candidateUsers($identifier);

        $matchingUsers = $candidates->filter(
            fn (User $user): bool => Hash::check($password, $user->password),
        );

        if ($matchingUsers->isEmpty()) {
            RateLimiter::hit($throttleKey, self::THROTTLE_DECAY_SECONDS);

            return $this->failedLogin('Username dan kata sandi tidak sesuai.');
        }

        if ($matchingUsers->count() > 1) {
            RateLimiter::hit($throttleKey, self::THROTTLE_DECAY_SECONDS);

            return $this->failedLogin(
                'Identitas ini cocok dengan lebih dari satu akun. Silakan hubungi administrator.',
            );
        }

        $user = $matchingUsers->first();

        if ($user->is_active === false) {
            RateLimiter::hit($throttleKey, self::THROTTLE_DECAY_SECONDS);

            return $this->failedLogin('Akun Anda tidak aktif. Silakan hubungi administrator.');
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->route($this->dashboardRouteFor($user->role));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Allow the single username field to be posted under its legacy names.
     */
    private function normalizeIdentifier(Request $request): void
    {
        if ($request->filled('username')) {
            return;
        }

        $identifier = $request->input('identifier')
            ?? $request->input('nis')
            ?? $request->input('nip');

        if ($identifier !== null) {
            $request->merge(['username' => $identifier]);
        }
    }

    /**
     * Collect every account that could own the entered NIS, NIP, or username.
     *
     * @return Collection<int, User>
     */
    private function candidateUsers(string $identifier): Collection
    {
        $usernames = User::query()
            ->where('username', Str::lower($identifier))
            ->get();

        $students = StudentProfile::query()
            ->with('user')
            ->where('nis', $identifier)
            ->get()
            ->pluck('user');

        $teachers = TeacherProfile::query()
            ->with('user')
            ->where('nip', $identifier)
            ->get()
            ->pluck('user');

        return $usernames
            ->merge($students)
            ->merge($teachers)
            ->filter(fn (?User $user): bool => $user instanceof User
                && in_array($user->role, self::ROLES, true))
            ->unique('id')
            ->values();
    }

    /**
     * Keep the entered username available after a failed attempt.
     */
    private function failedLogin(string $message): RedirectResponse
    {
        return back()
            ->withErrors(['username' => $message])
            ->onlyInput('username');
    }

    private function dashboardRouteFor(string $role): string
    {
        return match ($role) {
            'teacher' => 'teacher.dashboard',
            'admin' => 'admin.dashboard',
            default => 'student.dashboard',
        };
    }

    private function throttleKey(string $identifier, Request $request): string
    {
        return Str::lower($identifier.'|'.$request->ip());
    }
}
