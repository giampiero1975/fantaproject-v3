<?php

namespace App\Services\ApiFootball;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class LeagueRegistryMatcher
{
    /**
     * Provider-specific equivalenze ad alta confidenza.
     * Chiave: paese normalizzato + "|" + nome API-Football normalizzato.
     * Valore: nome canonico interno.
     *
     * @var array<string,string>
     */
    private const EXACT_ALIASES = [
        'belgium|jupiler pro league' => 'Belgian Pro League',
        'brazil|serie a' => 'Campeonato Brasileiro Série A',
        'brazil|serie b' => 'Campeonato Brasileiro Série B',
        'brazil|serie c' => 'Campeonato Brasileiro Série C',
        'brazil|serie d' => 'Campeonato Brasileiro Série D',
        'colombia|primera a' => 'Categoría Primera A',
        'colombia|primera b' => 'Categoría Primera B',
        'costa rica|primera division' => 'Liga FPD',
        'croatia|hnl' => 'Croatian Football League',
        'denmark|superliga' => 'Danish Superliga',
        'ecuador|liga pro' => 'LigaPro Serie A',
        'england|championship' => 'EFL Championship',
        'england|league one' => 'EFL League One',
        'england|league two' => 'EFL League Two',
        'france|national 1' => 'Championnat National',
        'hungary|nb i' => 'Nemzeti Bajnokság I',
        'hungary|nb ii' => 'Nemzeti Bajnokság II',
        'israel|ligat ha al' => 'Israeli Premier League',
        'peru|primera division' => 'Liga 1 Peru',
        'peru|segunda division' => 'Liga 2 Peru',
        'portugal|segunda liga' => 'Liga Portugal 2',
        'scotland|championship' => 'Scottish Championship',
        'scotland|league one' => 'Scottish League One',
        'scotland|league two' => 'Scottish League Two',
        'scotland|premiership' => 'Scottish Premiership',
        'ukraine|premier league' => 'Ukrainian Premier League',
        'uruguay|segunda division' => 'Segunda División Profesional de Uruguay',
        'wales|premier league' => 'Cymru Premier',
    ];

    public function __construct(private readonly LeagueNameNormalizer $normalizer)
    {
    }

    /**
     * @return array{status:string,candidates:array<int,array<string,mixed>>,reason:string}
     */
    public function match(
        string $externalName,
        ?string $externalCountry,
        ?string $externalCountryCode
    ): array {
        $countries = $this->candidateCountries(
            $externalCountry,
            $externalCountryCode
        );

        if ($countries->isEmpty()) {
            return [
                'status' => 'unmatched',
                'candidates' => [],
                'reason' => 'country_not_found',
            ];
        }

        $leagues = $this->candidateLeagues($countries);
        $externalNormalized = $this->normalizer->normalize($externalName);
        $countryNormalized = $this->normalizer->normalizeCountry(
            (string) ($countries->first()->name ?? $externalCountry ?? '')
        );

        // 1. Nome canonico identico dopo normalizzazione.
        $exact = $leagues->filter(
            fn (object $league): bool =>
                $this->normalizer->normalize((string) $league->league_name)
                === $externalNormalized
        );

        if ($exact->count() === 1) {
            return $this->matched($exact->first(), 'normalized_exact');
        }

        if ($exact->count() > 1) {
            return $this->ambiguous($exact, 'multiple_exact');
        }

        // 2. Alias API-Football espliciti e verificabili.
        $aliasKey = $countryNormalized.'|'.$externalNormalized;
        $canonicalAlias = self::EXACT_ALIASES[$aliasKey] ?? null;

        if ($canonicalAlias !== null) {
            $aliasNormalized = $this->normalizer->normalize($canonicalAlias);
            $aliasMatches = $leagues->filter(
                fn (object $league): bool =>
                    $this->normalizer->normalize((string) $league->league_name)
                    === $aliasNormalized
            );

            if ($aliasMatches->count() === 1) {
                return $this->matched(
                    $aliasMatches->first(),
                    'provider_alias_exact'
                );
            }
        }

        // 3. Confronto senza prefisso geografico/commerciale ridondante.
        $externalComparable = $this->normalizer->comparableLeagueName(
            $externalName,
            $countryNormalized
        );

        $comparableExact = $leagues->filter(
            fn (object $league): bool =>
                $this->normalizer->comparableLeagueName(
                    (string) $league->league_name,
                    $countryNormalized
                ) === $externalComparable
        );

        if ($externalComparable !== '' && $comparableExact->count() === 1) {
            return $this->matched(
                $comparableExact->first(),
                'country_context_exact'
            );
        }

        if ($externalComparable !== '' && $comparableExact->count() > 1) {
            return $this->ambiguous(
                $comparableExact,
                'multiple_country_context_exact'
            );
        }

        // 4. Scoring composito: similarità testuale + sovrapposizione token.
        $scored = $leagues
            ->map(function (object $league) use (
                $externalNormalized,
                $externalComparable,
                $countryNormalized
            ): array {
                $candidateNormalized = $this->normalizer->normalize(
                    (string) $league->league_name
                );
                $candidateComparable = $this->normalizer->comparableLeagueName(
                    (string) $league->league_name,
                    $countryNormalized
                );

                $fullSimilarity = $this->similarity(
                    $externalNormalized,
                    $candidateNormalized
                );
                $comparableSimilarity = $this->similarity(
                    $externalComparable,
                    $candidateComparable
                );
                $tokenScore = $this->tokenOverlap(
                    $externalComparable,
                    $candidateComparable
                );

                $score = round(
                    max($fullSimilarity, $comparableSimilarity) * 0.72
                    + $tokenScore * 0.28,
                    2
                );

                return $this->toArray($league) + [
                    'similarity' => $score,
                    'full_similarity' => round($fullSimilarity, 2),
                    'comparable_similarity' => round(
                        $comparableSimilarity,
                        2
                    ),
                    'token_overlap' => round($tokenScore, 2),
                ];
            })
            ->sortByDesc('similarity')
            ->values();

        $best = $scored->first();
        $second = $scored->get(1);

        // Match automatico solo con punteggio elevato e margine sufficiente.
        if (
            $best
            && $best['similarity'] >= 88.0
            && (
                ! $second
                || ($best['similarity'] - $second['similarity']) >= 10.0
            )
        ) {
            return [
                'status' => 'matched',
                'candidates' => [$best],
                'reason' => 'composite_high_confidence',
            ];
        }

        $review = $scored
            ->filter(fn (array $candidate): bool =>
                $candidate['similarity'] >= 62.0
            )
            ->take(5)
            ->values()
            ->all();

        // V3: se nel Paese esiste un solo candidato plausibile e il punteggio
        // è almeno 80, il match è sufficientemente forte per essere automatico.
        if (
            count($review) === 1
            && ($review[0]['similarity'] ?? 0.0) >= 80.0
        ) {
            return [
                'status' => 'matched',
                'candidates' => [$review[0]],
                'reason' => 'single_country_candidate_high_confidence',
            ];
        }

        return [
            'status' => $review === [] ? 'unmatched' : 'ambiguous',
            'candidates' => $review,
            'reason' => $review === []
                ? 'league_not_found'
                : 'manual_review_required',
        ];
    }

    private function candidateCountries(
        ?string $name,
        ?string $code
    ): Collection {
        $query = DB::table('countries')->where('active', true);

        if ($code !== null && trim($code) !== '') {
            $code = strtoupper(trim($code));

            $byCode = (clone $query)
                ->where(function ($builder) use ($code): void {
                    $builder
                        ->whereRaw('UPPER(iso2) = ?', [$code])
                        ->orWhereRaw('UPPER(iso3) = ?', [$code]);
                })
                ->get();

            if ($byCode->isNotEmpty()) {
                // Se API-Football fornisce anche il nome del Paese, il codice
                // non basta da solo: deve essere coerente con il nome.
                // Evita falsi positivi come Crimea (code UA) -> Ukraine.
                if ($name !== null && trim($name) !== '') {
                    $normalizedExternalName = $this->normalizer
                        ->normalizeCountry($name);

                    $byCode = $byCode
                        ->filter(
                            fn (object $country): bool =>
                                $this->normalizer->normalizeCountry(
                                    (string) $country->name
                                ) === $normalizedExternalName
                        )
                        ->values();
                }

                if ($byCode->isNotEmpty()) {
                    return $byCode;
                }
            }
        }

        if ($name === null || trim($name) === '') {
            return collect();
        }

        $normalized = $this->normalizer->normalizeCountry($name);

        return $query
            ->get()
            ->filter(
                fn (object $country): bool =>
                    $this->normalizer->normalizeCountry(
                        (string) $country->name
                    ) === $normalized
            )
            ->values();
    }

    private function candidateLeagues(Collection $countries): Collection
    {
        return DB::table('leagues')
            ->join('countries', 'countries.id', '=', 'leagues.country_id')
            ->whereIn('countries.id', $countries->pluck('id'))
            ->where('leagues.active', true)
            ->select([
                'leagues.id as league_id',
                'leagues.name as league_name',
                'leagues.slug as league_slug',
                'countries.id as country_id',
                'countries.name as country_name',
                'countries.iso2 as country_iso2',
                'countries.iso3 as country_iso3',
            ])
            ->get();
    }

    private function similarity(string $left, string $right): float
    {
        if ($left === '' || $right === '') {
            return 0.0;
        }

        similar_text($left, $right, $percentage);

        return $percentage;
    }

    private function tokenOverlap(string $left, string $right): float
    {
        $leftTokens = $this->normalizer->tokens($left);
        $rightTokens = $this->normalizer->tokens($right);

        if ($leftTokens === [] || $rightTokens === []) {
            return 0.0;
        }

        $intersection = count(array_intersect($leftTokens, $rightTokens));
        $union = count(array_unique(array_merge($leftTokens, $rightTokens)));

        return $union === 0 ? 0.0 : ($intersection / $union) * 100;
    }

    private function matched(object $league, string $reason): array
    {
        return [
            'status' => 'matched',
            'candidates' => [$this->toArray($league)],
            'reason' => $reason,
        ];
    }

    private function ambiguous(Collection $leagues, string $reason): array
    {
        return [
            'status' => 'ambiguous',
            'candidates' => $leagues
                ->map(fn (object $league): array => $this->toArray($league))
                ->values()
                ->all(),
            'reason' => $reason,
        ];
    }

    /** @return array<string,mixed> */
    private function toArray(object $league): array
    {
        return [
            'league_id' => (int) $league->league_id,
            'league_name' => (string) $league->league_name,
            'league_slug' => (string) $league->league_slug,
            'country_id' => (int) $league->country_id,
            'country_name' => (string) $league->country_name,
            'country_iso2' => (string) $league->country_iso2,
            'country_iso3' => (string) $league->country_iso3,
        ];
    }
}
