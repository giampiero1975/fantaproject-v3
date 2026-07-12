<?php

namespace App\Support\Registry;

final class LeagueNameNormalizer
{
    public static function normalize(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        if ($transliterated !== false) {
            $value = $transliterated;
        }

        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
