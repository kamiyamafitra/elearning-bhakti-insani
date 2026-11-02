<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

#[Signature('app:backup-database')]
#[Description('Save a copy of the database to private storage, keeping only the newest backups')]
class BackupDatabase extends Command
{
    public const DIRECTORY = 'backups';

    public const FILE_NAME_PATTERN = 'backup-\d{4}-\d{2}-\d{2}-\d{6}\.sql';

    public const KEEP_LATEST = 30;

    private const TIMEOUT_SECONDS = 300;

    /**
     * mysqldump writes this as its final line, so a file without it was cut short.
     */
    private const COMPLETED_MARKER = '-- Dump completed';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::DIRECTORY);

        $fileName = 'backup-'.now()->format('Y-m-d-His').'.sql';
        $finalPath = $disk->path(self::DIRECTORY."/{$fileName}");
        $partialPath = "{$finalPath}.partial";

        if (! $this->dumpTo($partialPath)) {
            if (is_file($partialPath)) {
                unlink($partialPath);
            }

            $this->error('Backup failed. See the application log for details.');

            return self::FAILURE;
        }

        rename($partialPath, $finalPath);
        $this->deleteOldBackups($disk);
        $this->info("Backup saved as {$fileName}.");

        return self::SUCCESS;
    }

    private function dumpTo(string $path): bool
    {
        $connection = config('database.connections.'.config('database.default'));

        try {
            $result = Process::env(['MYSQL_PWD' => (string) ($connection['password'] ?? '')])
                ->timeout(self::TIMEOUT_SECONDS)
                ->run([
                    config('database.dump_binary'),
                    '--host='.($connection['host'] ?? ''),
                    '--port='.($connection['port'] ?? ''),
                    '--user='.($connection['username'] ?? ''),
                    '--default-character-set=utf8mb4',
                    '--single-transaction',
                    '--no-tablespaces',
                    '--hex-blob',
                    "--result-file={$path}",
                    (string) ($connection['database'] ?? ''),
                ]);
        } catch (Throwable $exception) {
            Log::error('Database backup could not run.', ['exception' => $exception->getMessage()]);

            return false;
        }

        if ($result->failed() || ! $this->isComplete($path)) {
            Log::error('Database backup failed.', ['output' => $result->errorOutput()]);

            return false;
        }

        return true;
    }

    private function isComplete(string $path): bool
    {
        if (! is_file($path) || filesize($path) === 0) {
            return false;
        }

        $tailLength = min(filesize($path), 200);
        $tail = (string) file_get_contents($path, offset: filesize($path) - $tailLength);

        return str_contains($tail, self::COMPLETED_MARKER);
    }

    private function deleteOldBackups(Filesystem $disk): void
    {
        collect($disk->files(self::DIRECTORY))
            ->filter(fn (string $path) => Str::isMatch('/^'.self::FILE_NAME_PATTERN.'$/', basename($path)))
            ->sortDesc()
            ->slice(self::KEEP_LATEST)
            ->each(fn (string $path) => $disk->delete($path));
    }
}
