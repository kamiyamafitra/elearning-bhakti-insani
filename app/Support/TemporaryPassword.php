<?php

namespace App\Support;

/**
 * Random first-sign-in passwords for imported or reset accounts.
 *
 * Easy to read off a printed slip (no 0/o, 1/l/i look-alikes) yet not guessable like a NIS or NIP.
 * Accounts that receive one must still choose their own password when they first sign in.
 */
final class TemporaryPassword
{
    public const int LENGTH = 8;

    private const string ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public static function generate(): string
    {
        $password = '';
        $last = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::LENGTH; $i++) {
            $password .= self::ALPHABET[random_int(0, $last)];
        }

        return $password;
    }

    /**
     * @return list<string>
     */
    public static function many(int $count): array
    {
        return $count > 0 ? array_map(fn () => self::generate(), range(1, $count)) : [];
    }
}
