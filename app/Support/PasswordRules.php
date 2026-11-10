<?php

namespace App\Support;

/**
 * The one password rule for every account: at least 8 characters, with both letters and numbers.
 */
final class PasswordRules
{
    public const int MIN_LENGTH = 8;

    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['string', 'min:'.self::MIN_LENGTH, 'regex:/\pL/u', 'regex:/\pN/u'];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $field = 'password'): array
    {
        return [
            "{$field}.min" => 'Password minimal '.self::MIN_LENGTH.' karakter.',
            "{$field}.regex" => 'Password harus berisi huruf dan angka.',
        ];
    }
}
