<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Contracts\Encryption\DecryptException;

class StringHelper
{
    public static function decrypt(?string $code = null): ?string
    {
        if (empty($code)) {
            return null;
        }

        try {
            return (string) decrypt($code);
        } catch (DecryptException) {
            return null;
        }
    }

    public static function normalizeName(string $name): string
    {
        $name = \Illuminate\Support\Str::ascii(mb_strtolower(trim($name)));

        return trim((string) preg_replace('/\s+/', ' ', $name));
    }

    public static function namesAreSimilar(string $first, string $second, float $minPercent = 80.0): bool
    {
        $first = self::normalizeName($first);
        $second = self::normalizeName($second);

        if ($first === '' || $second === '') {
            return false;
        }

        if ($first === $second || str_contains($first, $second) || str_contains($second, $first)) {
            return true;
        }

        similar_text($first, $second, $percent);

        return $percent >= $minPercent;
    }

    public static function firstLetterCapitalized($string): string
    {
        $string = trim($string);
        $string = mb_convert_case($string, MB_CASE_TITLE, 'UTF-8');

        return str_replace([' Da ', ' De ', ' Do ', ' Das ', ' Dos '], [' da ', ' de ', ' do ', ' das ', ' dos '], $string);
    }
}
