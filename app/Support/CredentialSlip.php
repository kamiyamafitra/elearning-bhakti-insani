<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A one-time list of temporary passwords for the administrator to print and hand out.
 *
 * The list is stored encrypted in private storage, removed as soon as it is downloaded,
 * and anything older than a day is cleaned up, so plain passwords never linger on the server.
 */
final class CredentialSlip
{
    public const string DIRECTORY = 'credential-slips';

    private const int KEEP_HOURS = 24;

    /**
     * @param  list<array{name: string, identifier: string, group: string, password: string}>  $rows
     * @return string the slip id to download it with
     */
    public static function store(string $title, string $identifierLabel, array $rows): string
    {
        self::prune();

        $id = (string) Str::uuid();
        $payload = json_encode(['title' => $title, 'identifier_label' => $identifierLabel, 'rows' => $rows], JSON_THROW_ON_ERROR);

        Storage::disk('local')->put(self::path($id), Crypt::encryptString($payload));

        return $id;
    }

    /**
     * Read the slip and delete it, so the passwords can only be downloaded once.
     *
     * @return array{title: string, identifier_label: string, rows: list<array{name: string, identifier: string, group: string, password: string}>}|null
     */
    public static function pull(string $id): ?array
    {
        $disk = Storage::disk('local');
        $path = self::path($id);

        if (! Str::isUuid($id) || ! $disk->exists($path)) {
            return null;
        }

        try {
            $slip = json_decode(Crypt::decryptString($disk->get($path)), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            $slip = null;
        }

        $disk->delete($path);

        return $slip;
    }

    private static function prune(): void
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subHours(self::KEEP_HOURS)->getTimestamp();

        foreach ($disk->files(self::DIRECTORY) as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }
    }

    private static function path(string $id): string
    {
        return self::DIRECTORY."/{$id}.slip";
    }
}
