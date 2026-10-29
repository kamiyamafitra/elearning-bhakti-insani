<?php

namespace Tests\Feature;

use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_shows_a_single_username_field(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('SMK Bhakti Insani');
        $response->assertSee('Username (NIS / NIP)');
        $response->assertSee('Masukkan NIS, NIP, atau username administrator');
        $response->assertSee('Siswa memakai NIS');
        $response->assertSee('Masuk');
        $response->assertDontSee('Masuk sebagai Siswa');
        $response->assertDontSee('Masuk sebagai Guru');
        $response->assertDontSee('Masuk sebagai Administrator');
        $response->assertDontSee('data-bs-toggle="tab"', false);
        $response->assertDontSee('name="role"', false);
    }

    public function test_student_authenticates_with_nis_as_username(): void
    {
        $student = $this->createStudent('32587389573', 'kata-sandi-siswa');

        $response = $this->post('/login', [
            'username' => '32587389573',
            'password' => 'kata-sandi-siswa',
        ]);

        $response->assertRedirect(route('student.dashboard'));
        $response->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($student->user);
    }

    public function test_teacher_authenticates_with_nip_as_username(): void
    {
        $teacher = $this->createTeacher('198001012005011001', 'kata-sandi-guru');

        $response = $this->post('/login', [
            'username' => '198001012005011001',
            'password' => 'kata-sandi-guru',
        ]);

        $response->assertRedirect(route('teacher.dashboard'));
        $response->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($teacher->user);
    }

    public function test_admin_still_authenticates_with_username(): void
    {
        $admin = $this->createAdmin('admin', 'kata-sandi-admin');

        $response = $this->post('/login', [
            'username' => 'admin',
            'password' => 'kata-sandi-admin',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_username_authentication_is_case_insensitive(): void
    {
        $admin = $this->createAdmin('AdminUtama', 'kata-sandi-admin');

        $response = $this->post('/login', [
            'username' => 'ADMINUTAMA',
            'password' => 'kata-sandi-admin',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_legacy_role_payloads_still_authenticate(): void
    {
        $student = $this->createStudent('24001', 'kata-sandi-siswa');
        $teacher = $this->createTeacher('198001012005011001', 'kata-sandi-guru');

        $this->post('/login', [
            'identifier' => '24001',
            'password' => 'kata-sandi-siswa',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($student->user);

        $this->post('/logout')->assertRedirect(route('login'));

        $this->post('/login', [
            'nip' => '198001012005011001',
            'password' => 'kata-sandi-guru',
        ])->assertRedirect(route('teacher.dashboard'));

        $this->assertAuthenticatedAs($teacher->user);
    }

    public function test_incorrect_passwords_are_rejected_generically(): void
    {
        $student = $this->createStudent('24001', 'kata-sandi-siswa');
        $teacher = $this->createTeacher('198001012005011001', 'kata-sandi-guru');
        $admin = $this->createAdmin('admin', 'kata-sandi-admin');

        foreach (['24001', '198001012005011001', 'admin'] as $identifier) {
            $this->from('/login')->post('/login', [
                'username' => $identifier,
                'password' => 'kata-sandi-salah',
            ])->assertRedirect('/login')
                ->assertSessionHasErrors(['username' => 'Username dan kata sandi tidak sesuai.']);
        }

        $this->assertGuest();
        $this->assertDatabaseHas('student_profiles', ['id' => $student->id, 'nis' => '24001']);
        $this->assertDatabaseHas('teacher_profiles', ['id' => $teacher->id, 'nip' => '198001012005011001']);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
    }

    public function test_unknown_identifiers_are_rejected_with_the_same_generic_message(): void
    {
        $this->createStudent('24001', 'kata-sandi-siswa');
        $this->createTeacher('198001012005011001', 'kata-sandi-guru');

        foreach (['99999999999', '88888888888', 'tidak-ada'] as $identifier) {
            $this->from('/login')->post('/login', [
                'username' => $identifier,
                'password' => 'apa-saja',
            ])->assertRedirect('/login')
                ->assertSessionHasErrors(['username' => 'Username dan kata sandi tidak sesuai.']);
        }

        $this->assertGuest();
    }

    public function test_submitted_role_cannot_override_the_actual_account_role(): void
    {
        $student = $this->createStudent('24001', 'kata-sandi-siswa');

        $response = $this->post('/login', [
            'role' => 'admin',
            'username' => '24001',
            'password' => 'kata-sandi-siswa',
        ]);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($student->user);
    }

    public function test_colliding_identifiers_are_disambiguated_by_password(): void
    {
        $student = $this->createStudent('12345', 'kata-sandi-siswa');
        $teacher = $this->createTeacher('12345', 'kata-sandi-guru');

        $this->post('/login', [
            'username' => '12345',
            'password' => 'kata-sandi-siswa',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($student->user);

        $this->post('/logout')->assertRedirect(route('login'));

        $this->post('/login', [
            'username' => '12345',
            'password' => 'kata-sandi-guru',
        ])->assertRedirect(route('teacher.dashboard'));

        $this->assertAuthenticatedAs($teacher->user);
    }

    public function test_ambiguous_identifiers_with_the_same_password_are_rejected(): void
    {
        $this->createStudent('12345', 'kata-sandi-sama');
        $this->createTeacher('12345', 'kata-sandi-sama');

        $this->from('/login')->post('/login', [
            'username' => '12345',
            'password' => 'kata-sandi-sama',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors([
                'username' => 'Identitas ini cocok dengan lebih dari satu akun. Silakan hubungi administrator.',
            ]);

        $this->assertGuest();
    }

    public function test_username_and_profile_identifier_collisions_are_disambiguated(): void
    {
        $student = $this->createStudent('55555', 'kata-sandi-siswa');
        $admin = $this->createAdmin('55555', 'kata-sandi-admin');

        $this->post('/login', [
            'username' => '55555',
            'password' => 'kata-sandi-admin',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);

        $this->post('/logout')->assertRedirect(route('login'));

        $this->post('/login', [
            'username' => '55555',
            'password' => 'kata-sandi-siswa',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($student->user);
    }

    public function test_inactive_accounts_cannot_log_in(): void
    {
        $student = $this->createStudent('24001', 'kata-sandi-siswa', ['is_active' => false]);

        $this->from('/login')->post('/login', [
            'username' => '24001',
            'password' => 'kata-sandi-siswa',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors(['username' => 'Akun Anda tidak aktif. Silakan hubungi administrator.']);

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $student->user_id, 'is_active' => false]);
    }

    public function test_login_requires_username_and_password(): void
    {
        $this->from('/login')->post('/login', [
            'username' => '',
            'password' => '',
        ])->assertSessionHasErrors(['username', 'password']);

        $this->assertGuest();
    }

    public function test_students_cannot_access_teacher_or_admin_routes(): void
    {
        $student = $this->createStudent('24001', 'kata-sandi-siswa');

        $this->actingAs($student->user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->actingAs($student->user)
            ->get(route('teacher.dashboard'))
            ->assertForbidden();

        $this->assertAuthenticatedAs($student->user);
    }

    public function test_teachers_cannot_access_student_or_admin_routes(): void
    {
        $teacher = $this->createTeacher('198001012005011001', 'kata-sandi-guru');

        $this->actingAs($teacher->user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->actingAs($teacher->user)
            ->get(route('student.dashboard'))
            ->assertForbidden();

        $this->assertAuthenticatedAs($teacher->user);
    }

    public function test_each_role_can_open_its_own_dashboard(): void
    {
        $student = $this->createStudent('24001', 'kata-sandi-siswa');
        $teacher = $this->createTeacher('198001012005011001', 'kata-sandi-guru');
        $admin = $this->createAdmin('admin', 'kata-sandi-admin');

        $this->actingAs($student->user)->get(route('student.dashboard'))->assertOk();
        $this->actingAs($teacher->user)->get(route('teacher.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_login_does_not_modify_existing_student_or_teacher_records(): void
    {
        $student = $this->createStudent('24001', 'kata-sandi-siswa');
        $teacher = $this->createTeacher('198001012005011001', 'kata-sandi-guru');

        $studentAttributes = $student->user->only(['id', 'name', 'username', 'email', 'role', 'is_active', 'password']);
        $studentProfile = $student->only(['id', 'user_id', 'nis', 'nisn']);
        $teacherAttributes = $teacher->user->only(['id', 'name', 'username', 'email', 'role', 'is_active', 'password']);
        $teacherProfile = $teacher->only(['id', 'user_id', 'nip', 'phone', 'address']);

        $this->post('/login', [
            'username' => '24001',
            'password' => 'kata-sandi-siswa',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($student->user);

        $this->post('/logout')->assertRedirect(route('login'));

        $this->post('/login', [
            'username' => '198001012005011001',
            'password' => 'kata-sandi-guru',
        ])->assertRedirect(route('teacher.dashboard'));

        $this->assertAuthenticatedAs($teacher->user);

        $this->assertSame($studentAttributes, $student->user->fresh()->only(array_keys($studentAttributes)));
        $this->assertSame($studentProfile, $student->fresh()->only(array_keys($studentProfile)));
        $this->assertSame($teacherAttributes, $teacher->user->fresh()->only(array_keys($teacherAttributes)));
        $this->assertSame($teacherProfile, $teacher->fresh()->only(array_keys($teacherProfile)));
    }

    public function test_logout_invalidates_the_session(): void
    {
        $student = $this->createStudent('24001', 'kata-sandi-siswa');

        $this->actingAs($student->user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_repeated_failed_logins_are_throttled(): void
    {
        $this->createStudent('24001', 'kata-sandi-siswa');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')->post('/login', [
                'username' => '24001',
                'password' => 'kata-sandi-salah',
            ])->assertSessionHasErrors('username');
        }

        $this->from('/login')->post('/login', [
            'username' => '24001',
            'password' => 'kata-sandi-siswa',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertStringContainsString('Terlalu banyak percobaan masuk', session('errors')->first('username'));
    }

    /**
     * @param  array<string, mixed>  $userAttributes
     */
    private function createStudent(string $nis, string $password = 'password', array $userAttributes = []): StudentProfile
    {
        $user = User::factory()->create([
            'name' => 'Siswa '.$nis,
            'username' => 'student-'.Str::lower($nis),
            'email' => 'student-'.$nis.'@example.test',
            'password' => $password,
            'role' => 'student',
            'is_active' => true,
            ...$userAttributes,
        ]);

        return StudentProfile::create([
            'user_id' => $user->id,
            'nis' => $nis,
        ]);
    }

    /**
     * @param  array<string, mixed>  $userAttributes
     */
    private function createTeacher(string $nip, string $password = 'password', array $userAttributes = []): TeacherProfile
    {
        $user = User::factory()->create([
            'name' => 'Guru '.$nip,
            'username' => 'teacher-'.Str::lower($nip),
            'email' => 'teacher-'.$nip.'@example.test',
            'password' => $password,
            'role' => 'teacher',
            'is_active' => true,
            ...$userAttributes,
        ]);

        return TeacherProfile::create([
            'user_id' => $user->id,
            'nip' => $nip,
        ]);
    }

    /**
     * @param  array<string, mixed>  $userAttributes
     */
    private function createAdmin(string $username, string $password = 'password', array $userAttributes = []): User
    {
        return User::factory()->create([
            'name' => 'Administrator '.$username,
            'username' => $username,
            'email' => Str::lower($username).'@example.test',
            'password' => $password,
            'role' => 'admin',
            'is_active' => true,
            ...$userAttributes,
        ]);
    }
}
