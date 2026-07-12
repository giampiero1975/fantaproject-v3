<?php

namespace App\Services\ApiFootball;

use Illuminate\Support\Str;

final class LeagueNameNormalizer
{
    /** @var array<string,string> */
    private const COUNTRY_ALIASES = [
        'bosnia' => 'bosnia and herzegovina',
        'bosnia herzegovina' => 'bosnia and herzegovina',
        'czech republic' => 'czechia',
        'england' => 'england',
        'faroe islands' => 'faroe islands',
        'ireland' => 'republic of ireland',
        'korea republic' => 'south korea',
        'macedonia' => 'north macedonia',
        'northern ireland' => 'northern ireland',
        'republic of ireland' => 'republic of ireland',
        'russia' => 'russia',
        'scotland' => 'scotland',
        'south korea' => 'south korea',
        'turkey' => 'turkiye',
        'united states' => 'united states',
        'united states of america' => 'united states',
        'usa' => 'united states',
        'wales' => 'wales',
    ];

    /** @var array<string,string> */
    private const ORDINALS = [
        'first' => '1',
        '1st' => '1',
        'second' => '2',
        '2nd' => '2',
        'third' => '3',
        '3rd' => '3',
        'fourth' => '4',
        '4th' => '4',
    ];

    public function normalize(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value)));
        $value = str_replace(['&', '+'], ' and ', $value);
        $value = preg_replace('/\b(the|football|soccer)\b/u', ' ', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        $tokens = preg_split('/\s+/', $value) ?: [];
        $tokens = array_map(
            static fn (string $token): string => self::ORDINALS[$token] ?? $token,
            $tokens
        );

        return implode(' ', $tokens);
    }

    public function normalizeCountry(string $value): string
    {
        $normalized = $this->normalize($value);

        return self::COUNTRY_ALIASES[$normalized] ?? $normalized;
    }

    /**
     * Riduce soltanto elementi geografici/commerciali ridondanti.
     * Non elimina termini strutturali come league, division, serie o liga.
     */
    public function comparableLeagueName(string $value, ?string $countryName = null): string
    {
        $normalized = $this->normalize($value);

        if ($countryName !== null && trim($countryName) !== '') {
            $country = $this->normalizeCountry($countryName);
            $countryTokens = preg_split('/\s+/', $country) ?: [];

            $nationalAdjectives = [
                'belgium' => ['belgian'],
                'denmark' => ['danish'],
                'england' => ['english', 'efl'],
                'scotland' => ['scottish'],
                'ukraine' => ['ukrainian'],
                'wales' => ['welsh'],
                'ireland' => ['irish'],
                'republic of ireland' => ['irish'],
                'norway' => ['norwegian'],
                'switzerland' => ['swiss'],
                'austria' => ['austrian'],
                'croatia' => ['croatian'],
                'serbia' => ['serbian'],
                'slovenia' => ['slovenian'],
                'slovakia' => ['slovak'],
                'montenegro' => ['montenegrin'],
                'macedonia' => ['macedonian'],
                'north macedonia' => ['macedonian'],
                'greece' => ['greek'],
                'israel' => ['israeli'],
                'russia' => ['russian'],
                'brazil' => ['brasileiro', 'brasileira', 'brazilian', 'campeonato'],
            ];

            $remove = array_merge(
                $countryTokens,
                $nationalAdjectives[$country] ?? []
            );

            $tokens = preg_split('/\s+/', $normalized) ?: [];
            $tokens = array_values(array_filter(
                $tokens,
                static fn (string $token): bool => ! in_array($token, $remove, true)
            ));

            $normalized = implode(' ', $tokens);
        }

        // Nomi commerciali ricorrenti che non descrivono la divisione.
        $commercial = [
            'jupiler',
            'bwin',
            'betclic',
            'cabovisao',
            'cinch',
            'ligue 1 uber eats',
            'liga nos',
        ];

        foreach ($commercial as $token) {
            $normalized = preg_replace(
                '/\b'.preg_quote($token, '/').'\b/u',
                ' ',
                $normalized
            ) ?? $normalized;
        }

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
    }

    /** @return array<int,string> */
    public function tokens(string $value): array
    {
        $normalized = $this->normalize($value);

        return $normalized === ''
            ? []
            : array_values(array_unique(preg_split('/\s+/', $normalized) ?: []));
    }
}
