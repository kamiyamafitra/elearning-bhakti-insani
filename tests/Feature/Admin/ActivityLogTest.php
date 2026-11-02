<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Classes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    public function test_successful_login_and_logout_are_logged(): void
    {
        $admin = $this->admin(['username' => 'kepala', 'password' => Hash::make('rahasia123')]);

        $this->post(route('login'), ['username' => 'kepala', 'password' => 'rahasia123']);
        $this->post(route('logout'));

        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'action' => 'login', 'description' => 'masuk ke aplikasi']);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'action' => 'logout']);
    }

    public function test_failed_login_records_the_attempted_username_and_ip(): void
    {
        $this->post(route('login'), ['username' => 'penyusup', 'password' => 'salah'], ['REMOTE_ADDR' => '10.0.0.7']);

        $log = ActivityLog::where('action', 'login_failed')->sole();

        $this->assertNull($log->user_id);
        $this->assertSame('gagal masuk dengan username "pe******"', $log->description);
        $this->assertSame('pe******', $log->properties['identifier']);
        $this->assertSame('10.0.0.7', $log->ip_address);
    }

    public function test_creating_updating_and_deleting_data_is_logged_with_changes(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $class = $this->makeClass('X TKJ 1');
        $class->update(['name' => 'X TKJ 2']);
        $class->delete();

        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'action' => 'created', 'description' => 'menambahkan kelas "X TKJ 1"']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'deleted', 'description' => 'menghapus kelas "X TKJ 2"', 'subject_type' => Classes::class]);

        $update = ActivityLog::where('action', 'updated')->where('subject_type', Classes::class)->sole();
        $this->assertSame('mengubah kelas "X TKJ 2"', $update->description);
        $this->assertSame(['name' => ['X TKJ 1', 'X TKJ 2']], $update->properties['changes']);
    }

    public function test_password_changes_are_noted_without_storing_the_password(): void
    {
        $student = $this->makeStudent($this->makeClass(), 'Andi', '24001');
        $student->update(['password' => Hash::make('24001')]);
        $this->actingAs($student);

        $this->put(route('password.update'), [
            'current_password' => '24001',
            'password' => 'rahasia-baru',
            'password_confirmation' => 'rahasia-baru',
        ]);

        $log = ActivityLog::where('action', 'updated')->where('subject_id', $student->id)->where('subject_type', User::class)->latest('id')->first();

        $this->assertTrue($log->properties['password_changed']);
        $this->assertStringNotContainsString('rahasia-baru', json_encode($log->properties));
        $this->assertArrayNotHasKey('password', $log->properties['changes'] ?? []);
    }

    public function test_bulk_import_is_logged_once_as_a_summary(): void
    {
        $admin = $this->admin();
        $createdBefore = ActivityLog::where('action', 'created')->count();

        $this->actingAs($admin)->post(route('admin.students.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('siswa.csv', "nama,nis\nAndi,24001\nBunga,24002\nCitra,24003"),
        ])->assertRedirect(route('admin.students.index'));

        $this->assertSame($createdBefore, ActivityLog::where('action', 'created')->count());
        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'action' => 'bulk', 'description' => 'mengimpor 3 siswa dari file']);
    }

    public function test_admin_page_lists_and_filters_logs(): void
    {
        $admin = $this->admin(['name' => 'Kepala Admin']);
        $teacher = $this->makeTeacher('Budi Santoso');
        ActivityLog::factory()->for($teacher)->create(['action' => 'created', 'description' => 'menambahkan materi "Jaringan"']);
        ActivityLog::factory()->create(['user_id' => null, 'action' => 'login_failed', 'description' => 'gagal masuk dengan username "x"']);

        $this->actingAs($admin)->get(route('admin.activity-logs.index'))
            ->assertOk()
            ->assertSee('menambahkan materi &quot;Jaringan&quot;', false)
            ->assertSee('Budi Santoso')
            ->assertSee('Tidak dikenal')
            ->assertSee('percobaan masuk yang gagal hari ini');

        $this->actingAs($admin)->get(route('admin.activity-logs.index', ['action' => 'login_failed']))
            ->assertSee('gagal masuk dengan username')
            ->assertDontSee('menambahkan materi');

        $this->actingAs($admin)->get(route('admin.activity-logs.index', ['q' => 'Budi']))
            ->assertSee('menambahkan materi')
            ->assertDontSee('gagal masuk dengan username');
    }

    public function test_non_admins_cannot_see_the_log(): void
    {
        $this->actingAs($this->makeTeacher())->get(route('admin.activity-logs.index'))->assertForbidden();
    }

    public function test_old_entries_are_pruned(): void
    {
        $old = ActivityLog::factory()->create(['created_at' => now()->subDays(ActivityLog::RETENTION_DAYS + 1)]);
        $recent = ActivityLog::factory()->create(['created_at' => now()->subDays(10)]);

        Artisan::call('model:prune', ['--model' => [ActivityLog::class]]);

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function admin(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => 'admin', 'is_active' => true], $attributes));
    }
}
