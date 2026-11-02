<?php

namespace Tests\Feature\Admin;

use App\Console\Commands\BackupDatabase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\CreatesAcademicData;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use CreatesAcademicData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_admin_can_create_a_backup_and_it_is_logged(): void
    {
        $this->fakeDump("-- MySQL dump\n-- Dump completed on 2026-11-02\n");

        $this->actingAs($this->admin())
            ->from(route('admin.backups.index'))
            ->post(route('admin.backups.store'))
            ->assertRedirect(route('admin.backups.index'))
            ->assertSessionHas('success');

        $this->assertCount(1, Storage::disk('local')->files('backups'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'bulk', 'description' => 'membuat backup database']);
    }

    public function test_password_is_not_passed_on_the_command_line(): void
    {
        config(['database.connections.'.config('database.default').'.password' => 'rahasia-db']);
        $this->fakeDump("-- Dump completed\n");

        $this->artisan(BackupDatabase::class)->assertSuccessful();

        Process::assertRan(fn (PendingProcess $process) => $process->environment['MYSQL_PWD'] === 'rahasia-db'
            && ! str_contains(implode(' ', $process->command), 'rahasia-db'));
    }

    public function test_failed_dump_leaves_no_file_and_shows_an_error(): void
    {
        Process::fake(fn () => Process::result(errorOutput: 'Access denied', exitCode: 2));

        $this->actingAs($this->admin())
            ->from(route('admin.backups.index'))
            ->post(route('admin.backups.store'))
            ->assertSessionHas('error');

        $this->assertSame([], Storage::disk('local')->files('backups'));
    }

    public function test_truncated_dump_is_rejected(): void
    {
        $this->fakeDump("-- MySQL dump\nINSERT INTO `users` VALUES (1");

        $this->artisan(BackupDatabase::class)->assertFailed();

        $this->assertSame([], Storage::disk('local')->files('backups'));
    }

    public function test_only_the_newest_backups_are_kept(): void
    {
        $disk = Storage::disk('local');
        $oldest = 'backups/backup-2020-01-01-000000.sql';
        $disk->put($oldest, 'lama');
        $disk->put('backups/catatan.txt', 'bukan backup');

        for ($day = 10; $day < 10 + BackupDatabase::KEEP_LATEST; $day++) {
            $disk->put("backups/backup-2021-01-{$day}-000000.sql", 'isi');
        }

        $this->fakeDump("-- Dump completed\n");
        $this->artisan(BackupDatabase::class)->assertSuccessful();

        $disk->assertMissing($oldest);
        $disk->assertExists('backups/catatan.txt');
        $this->assertCount(BackupDatabase::KEEP_LATEST + 1, $disk->files('backups'));
    }

    public function test_page_lists_backups_and_warns_when_there_are_none_recent(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.backups.index'))->assertOk()->assertSee('Belum ada backup.');

        Storage::disk('local')->put('backups/backup-2020-01-01-080000.sql', 'isi');

        $this->actingAs($admin)->get(route('admin.backups.index'))
            ->assertSee('backup-2020-01-01-080000.sql')
            ->assertSee('01-01-2020 08:00')
            ->assertSee('lebih dari seminggu');

        Storage::disk('local')->put('backups/backup-'.now()->format('Y-m-d-His').'.sql', 'isi');

        $this->actingAs($admin)->get(route('admin.backups.index'))->assertDontSee('lebih dari seminggu');
    }

    public function test_admin_can_download_and_delete_a_backup(): void
    {
        $admin = $this->admin();
        $name = 'backup-2026-01-01-080000.sql';
        Storage::disk('local')->put("backups/{$name}", 'isi backup');

        $this->actingAs($admin)->get(route('admin.backups.download', $name))->assertDownload($name);
        $this->assertDatabaseHas('activity_logs', ['description' => "mengunduh backup database {$name}"]);

        $this->actingAs($admin)->delete(route('admin.backups.destroy', $name))->assertRedirect();
        Storage::disk('local')->assertMissing("backups/{$name}");
    }

    public function test_paths_outside_the_backup_pattern_cannot_be_reached(): void
    {
        $admin = $this->admin();
        Storage::disk('local')->put('rahasia.sql', 'isi');

        $this->actingAs($admin)->get('/admin/backups/..%2Frahasia.sql/download')->assertNotFound();
        $this->actingAs($admin)->get('/admin/backups/backup-2026-01-01-080000.sql/download')->assertNotFound();
        $this->actingAs($admin)->delete('/admin/backups/rahasia.sql')->assertNotFound();
    }

    public function test_non_admins_cannot_reach_backups(): void
    {
        $name = 'backup-2026-01-01-080000.sql';
        Storage::disk('local')->put("backups/{$name}", 'isi');

        foreach ([$this->makeTeacher(), $this->makeStudent($this->makeClass(), 'Andi', '24001')] as $user) {
            $this->actingAs($user)->get(route('admin.backups.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.backups.store'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.backups.download', $name))->assertForbidden();
            $this->actingAs($user)->delete(route('admin.backups.destroy', $name))->assertForbidden();
        }

        Storage::disk('local')->assertExists("backups/{$name}");
    }

    /**
     * Fake mysqldump by writing the given content to the requested result file.
     */
    private function fakeDump(string $content): void
    {
        Process::fake(function (PendingProcess $process) use ($content) {
            $resultFile = collect($process->command)->first(fn (string $argument) => Str::startsWith($argument, '--result-file='));
            file_put_contents(Str::after($resultFile, '--result-file='), $content);

            return Process::result();
        });
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }
}
