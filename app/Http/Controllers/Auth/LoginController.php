<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
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
     * Failed logins allowed from one IP address, across all accounts, before that address is paused.
     * Generous because a whole school often shares one public IP; it stops one machine guessing many accounts.
     */
    private const MAX_FAILED_LOGINS_PER_IP = 60;

    private const IP_THROTTLE_DECAY_SECONDS = 600;

    /**
     * The number of failed logins recorded per IP address each minute.
     */
    private const MAX_LOGGED_FAILURES_PER_MINUTE = 20;

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
        $ipThrottleKey = 'login-ip|'.$request->ip();

        if (RateLimiter::tooManyAttempts($ipThrottleKey, self::MAX_FAILED_LOGINS_PER_IP)) {
            return $this->loginError(
                'Terlalu banyak percobaan masuk yang gagal dari jaringan ini. Silakan coba lagi dalam '
                    .ceil(RateLimiter::availableIn($ipThrottleKey) / 60).' menit.',
            );
        }

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            return $this->loginError(
                'Terlalu banyak percobaan masuk. Silakan coba lagi dalam '
                    .RateLimiter::availableIn($throttleKey).' detik.',
            );
        }

        $candidates = $this->candidateUsers($identifier);

        // Check a dummy hash for unknown usernames so the response time doesn't reveal which accounts exist.
        if ($candidates->isEmpty()) {
            Hash::check($password, self::dummyHash());
        }

        $matchingUsers = $candidates->filter(
            fn (User $user): bool => Hash::check($password, $user->password),
        );

        if ($matchingUsers->isEmpty()) {
            RateLimiter::hit($throttleKey, self::THROTTLE_DECAY_SECONDS);
            RateLimiter::hit($ipThrottleKey, self::IP_THROTTLE_DECAY_SECONDS);

            return $this->failedLogin('Username dan kata sandi tidak sesuai.', $candidates->isNotEmpty());
        }

        if ($matchingUsers->count() > 1) {
            RateLimiter::hit($throttleKey, self::THROTTLE_DECAY_SECONDS);
            RateLimiter::hit($ipThrottleKey, self::IP_THROTTLE_DECAY_SECONDS);

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
     * Log a failed attempt, capped per IP address so a script cannot flood the log.
     *
     * Unknown usernames are masked because people sometimes type their password into the username field.
     */
    private function failedLogin(string $message, bool $isKnownAccount = true): RedirectResponse
    {
        $identifier = Str::limit(trim((string) request()->input('username')), 100);
        $loggedIdentifier = $isKnownAccount ? $identifier : Str::mask($identifier, '*', 2);

        RateLimiter::attempt(
            'login-failure-log|'.request()->ip(),
            self::MAX_LOGGED_FAILURES_PER_MINUTE,
            fn () => ActivityLog::record(
                ActivityLog::ACTION_LOGIN_FAILED,
                "gagal masuk dengan username \"{$loggedIdentifier}\"",
                properties: ['identifier' => $loggedIdentifier, 'reason' => $message],
            ),
        );

        return $this->loginError($message);
    }

    /**
     * Keep the entered username available after a failed attempt.
     */
    private function loginError(string $message): RedirectResponse
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

    /**
     * A fixed bcrypt hash of a random, discarded value, at the same cost (12) as real passwords,
     * so checking it takes as long as checking a real account. It matches no password.
     */
    private static function dummyHash(): string
    {
        return '$2y$12$1FdxJfC6W3FBvMeD/kEteu59vSoov7mzzBi8YIpeIDgPeU9KunGxa';
    }

    private function throttleKey(string $identifier, Request $request): string
    {
        return Str::lower($identifier.'|'.$request->ip());
    }
}
