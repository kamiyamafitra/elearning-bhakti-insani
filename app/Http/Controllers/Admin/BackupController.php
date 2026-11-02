<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\BackupDatabase;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    /**
     * The admin is warned when the newest backup is older than this.
     */
    private const STALE_AFTER_DAYS = 7;

    public function index(): View
    {
        $disk = Storage::disk('local');

        $backups = collect($disk->files(BackupDatabase::DIRECTORY))
            ->map(fn (string $path) => basename($path))
            ->filter(fn (string $name) => Str::isMatch('/^'.BackupDatabase::FILE_NAME_PATTERN.'$/', $name))
            ->sortDesc()
            ->values()
            ->map(fn (string $name) => [
                'name' => $name,
                'size' => $disk->size(BackupDatabase::DIRECTORY."/{$name}"),
                'created_at' => CarbonImmutable::createFromFormat('Y-m-d-His', Str::between($name, 'backup-', '.sql')),
            ]);

        $latestBackup = $backups->first();

        return view('admin.backups.index', [
            'backups' => $backups,
            'keepLatest' => BackupDatabase::KEEP_LATEST,
            'isStale' => $latestBackup === null || $latestBackup['created_at']->lt(now()->subDays(self::STALE_AFTER_DAYS)),
        ]);
    }

    public function store(): RedirectResponse
    {
        set_time_limit(360);

        if (Artisan::call(BackupDatabase::class) !== BackupDatabase::SUCCESS) {
            return back()->with('error', 'Backup gagal dibuat. Pastikan mysqldump tersedia, lalu periksa log aplikasi.');
        }

        ActivityLog::record(ActivityLog::ACTION_BULK, 'membuat backup database');

        return back()->with('success', 'Backup berhasil dibuat. Unduh dan simpan file di tempat yang aman.');
    }

    public function download(string $backup): BinaryFileResponse
    {
        ActivityLog::record(ActivityLog::ACTION_BULK, "mengunduh backup database {$backup}");

        return response()->download($this->pathOf($backup));
    }

    public function destroy(string $backup): RedirectResponse
    {
        unlink($this->pathOf($backup));

        ActivityLog::record(ActivityLog::ACTION_BULK, "menghapus backup database {$backup}");

        return back()->with('success', 'Backup berhasil dihapus.');
    }

    private function pathOf(string $backup): string
    {
        $path = BackupDatabase::DIRECTORY."/{$backup}";

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->path($path);
    }
}
