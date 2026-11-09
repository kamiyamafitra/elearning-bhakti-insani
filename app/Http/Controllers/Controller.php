<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Uploaded files that can be opened in the in-app preview, by stored file extension.
     *
     * @var array<string, array{type: string, mime: string}>
     */
    public const PREVIEWABLE_FILES = [
        'pdf' => ['type' => 'pdf', 'mime' => 'application/pdf'],
        'txt' => ['type' => 'text', 'mime' => 'text/plain; charset=UTF-8'],
        'jpg' => ['type' => 'image', 'mime' => 'image/jpeg'],
        'jpeg' => ['type' => 'image', 'mime' => 'image/jpeg'],
        'png' => ['type' => 'image', 'mime' => 'image/png'],
        'webp' => ['type' => 'image', 'mime' => 'image/webp'],
        'docx' => ['type' => 'word', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xlsx' => ['type' => 'sheet', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'xls' => ['type' => 'sheet', 'mime' => 'application/vnd.ms-excel'],
        'ods' => ['type' => 'sheet', 'mime' => 'application/vnd.oasis.opendocument.spreadsheet'],
    ];

    /**
     * The preview kind for a stored file, or null when it can only be downloaded.
     * The stored path is used because its extension comes from the file's contents, not its upload name.
     */
    public static function previewTypeFor(?string $storedPath): ?string
    {
        return self::PREVIEWABLE_FILES[strtolower(pathinfo((string) $storedPath, PATHINFO_EXTENSION))]['type'] ?? null;
    }

    /**
     * Send an uploaded file: shown in the browser for "?inline=1" when it is previewable, downloaded otherwise.
     */
    protected function uploadedFileResponse(Request $request, string $path, ?string $name): Response
    {
        $name = $name ?: basename($path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! $request->boolean('inline') || ! isset(self::PREVIEWABLE_FILES[$extension])) {
            return Storage::disk('local')->download($path, $name);
        }

        $fallbackName = preg_replace('/[^\x20-\x7E]|[\/\\\\%"]/', '_', $name) ?: 'berkas';

        // A fixed type plus nosniff and a sandbox keep an uploaded text file from ever running as a web page.
        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => self::PREVIEWABLE_FILES[$extension]['mime'],
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', $name, $fallbackName),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'; img-src 'self'; style-src 'unsafe-inline'",
            'Cache-Control' => 'private, max-age=600',
        ]);
    }
}
