<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait HasPrivateImage
{
    protected static function bootHasPrivateImage(): void
    {
        static::deleted(function (self $model): void {
            if ($model->image_path) {
                Storage::disk('local')->delete($model->image_path);
            }
        });
    }

    public function imageResponse(): BinaryFileResponse
    {
        abort_unless($this->image_path && Storage::disk('local')->exists($this->image_path), 404);

        return response()->file(Storage::disk('local')->path($this->image_path), [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
