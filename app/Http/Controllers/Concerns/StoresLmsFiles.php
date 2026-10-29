<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait StoresLmsFiles
{
    /**
     * @return array{path: string, name: string, mime: string|null, size: int|null}
     */
    protected function storeLmsFile(UploadedFile $file, string $directory): array
    {
        return [
            'path' => $file->store($directory, 'local'),
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ];
    }

    protected function deleteLmsFile(?string $path): void
    {
        if (filled($path)) {
            Storage::disk('local')->delete($path);
        }
    }
}
