<?php

namespace App\Services\Tiers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TeamTieringService
{
    /** @var array<string,array<string,mixed>>|null */
    private ?array $settings = null;

    /** @param array<string,array<string,mixed>> $settingOverrides */
    public function __construct(private readonly array $settingOverrides = [])
    {
    }

    /** @return array<string,mixed> */
    public function analyze(int $leagueSeasonId): array
    {
        $context = $this->leagueSeasonContext($leagueSeasonId);
        $teams = $this->targetTeams($leagueSeasonId);
        $seasonKeys = $this->lookbackSeasonKeys((int) $context['season_key']);
        $rows = $teams
            ->map(fn (object $team): array => $this->scoreTeam($team, $leagueSeasonId, $seasonKeys, (string) $context['league_name']))
            ->sortBy([
                ['score', 'asc'],
                ['team_name', 'asc'],
            ])
            ->values()
            ->all();

        return [
            'status' => $this->statusForRows($rows),
            'league_season' => $context,
            'season_keys' => $seasonKeys,
            'rows' => $rows,
            'distribution' => collect($rows)->countBy('tier')->sortKeys()->all(),
        ];
    }

    /** @param array<string,mixed> $report */
    public function apply(array $report): void
    {
        DB::transaction(function () use ($report): void {
            $isCurrentLeagueSeason = (bool) ($report['league_season']['is_current'] ?? false);

            foreach ($report['rows'] as $row) {
                if (! in_array($row['action'], ['CREATE', 'UPDATE'], true)) {
                    continue;
                }

                if ($isCurrentLeagueSeason) {
                    DB::table('teams')->where('id', $row['team_id'])->update([
                        'tier_globale' => $row['tier'],
                        'posizione_media_storica' => $row['score'],
                        'updated_at' => now(),
                    ]);
                }

                DB::table('league_season_teams')->where('id', $row['league_season_team_id'])->update([
                    'tier_stagionale' => $row['tier'],
                    'tier_score' => $row['score'],
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /** @return array<string,mixed> */
    public function performanceAudit(int $leagueSeasonId): array
    {
        $context = $this->leagueSeasonContext($leagueSeasonId);
        $competitionSize = DB::table('league_season_teams')
            ->where('league_season_id', $leagueSeasonId)
            ->where('is_active', true)
            ->count();
        $rows = DB::table('league_season_teams as lst')
            ->join('teams as t', 't.id', '=', 'lst.team_id')
            ->leftJoin('league_season_team_standings as st', 'st.league_season_team_id', '=', 'lst.id')
            ->where('lst.league_season_id', $leagueSeasonId)
            ->where('lst.is_active', true)
            ->select([
                'lst.id as league_season_team_id',
                'lst.tier_stagionale',
                'lst.tier_score',
                't.name as team_name',
                'st.position',
                'st.played_games',
                'st.points',
                'st.goals_for',
                'st.goals_against',
                'st.goal_difference',
                'st.stage_name',
                'st.group_name',
            ])
            ->orderByRaw('case when st.stage_name is null then 0 else 1 end')
            ->orderByRaw('case when st.group_name is null then 0 else 1 end')
            ->get()
            ->unique('league_season_team_id')
            ->map(function (object $row) use ($context, $competitionSize): array {
                $expectedScore = $row->tier_score === null ? null : round((float) $row->tier_score, 4);
                $expectedTier = $row->tier_stagionale === null ? null : (int) $row->tier_stagionale;
                $actualScore = $row->position === null ? null : round($this->seasonScore((object) [
                    'league_name' => $context['league_name'],
                    'played_games' => $row->played_games,
                    'points' => $row->points,
                    'goals_for' => $row->goals_for,
                    'goals_against' => $row->goals_against,
                    'position' => $row->position,
                    'competition_size' => $competitionSize,
                ]), 4);
                $actualTier = $actualScore === null ? null : $this->tierForScore($actualScore);
                $scoreDelta = $actualScore !== null && $expectedScore !== null
                    ? round($actualScore - $expectedScore, 4)
                    : null;

                return [
                    'team_name' => $row->team_name,
                    'expected_tier' => $expectedTier,
                    'expected_score' => $expectedScore,
                    'actual_tier' => $actualTier,
                    'actual_score' => $actualScore,
                    'score_delta' => $scoreDelta,
                    'position' => $row->position === null ? null : (int) $row->position,
                    'points' => $row->points === null ? null : (int) $row->points,
                    'goals_for' => $row->goals_for === null ? null : (int) $row->goals_for,
                    'goals_against' => $row->goals_against === null ? null : (int) $row->goals_against,
                    'status' => $this->performanceStatus($expectedTier, $actualTier, $scoreDelta),
                ];
            })
            ->sortBy([
                ['actual_score', 'asc'],
                ['team_name', 'asc'],
            ])
            ->values()
            ->all();

        return [
            'status' => $this->performanceAuditStatus($rows),
            'league_season' => $context,
            'rows' => $rows,
            'summary' => collect($rows)->countBy('status')->sortKeys()->all(),
        ];
    }

    /**
     * Recalculates every selected historical season using only data available
     * before that season, then compares the predicted ranking with reality.
     *
     * @param list<int> $leagueSeasonIds
     * @return array<string,mixed>
     */
    public function walkForwardAudit(array $leagueSeasonIds): array
    {
        $seasons = collect($leagueSeasonIds)
            ->map(function (int $leagueSeasonId): array {
                $analysis = $this->analyze($leagueSeasonId);
                $actualByTeam = collect($this->performanceAudit($leagueSeasonId)['rows'])
                    ->keyBy('team_name');

                $rows = collect($analysis['rows'])
                    ->values()
                    ->map(function (array $row, int $index) use ($actualByTeam): array {
                        $actual = $actualByTeam->get($row['team_name']);

                        return [
                            'team_name' => $row['team_name'],
                            'predicted_position' => $index + 1,
                            'actual_position' => $actual['position'] ?? null,
                            'predicted_tier' => $row['tier'],
                            'actual_tier' => $actual['actual_tier'] ?? null,
                        ];
                    })
                    ->whereNotNull('actual_position')
                    ->values();

                return [
                    'league_season' => $analysis['league_season'],
                    'metrics' => $this->rankingMetrics($rows),
                    'rows' => $rows->all(),
                ];
            })
            ->values();

        $validSeasons = $seasons->filter(fn (array $season): bool => ($season['metrics']['teams'] ?? 0) > 1);

        return [
            'status' => $validSeasons->count() === $seasons->count() ? 'ready' : 'attention_required',
            'summary' => [
                'seasons' => $validSeasons->count(),
                'teams' => $validSeasons->sum(fn (array $season): int => (int) $season['metrics']['teams']),
                'ranking_accuracy_pct' => round((float) $validSeasons->avg('metrics.ranking_accuracy_pct'), 2),
                'position_mae' => round((float) $validSeasons->avg('metrics.position_mae'), 2),
                'exact_tier_pct' => round((float) $validSeasons->avg('metrics.exact_tier_pct'), 2),
                'within_one_tier_pct' => round((float) $validSeasons->avg('metrics.within_one_tier_pct'), 2),
            ],
            'seasons' => $seasons->all(),
        ];
    }

    /** @return array<string,mixed> */
    private function leagueSeasonContext(int $leagueSeasonId): array
    {
        $row = DB::table('league_seasons as ls')
            ->join('leagues as l', 'l.id', '=', 'ls.league_id')
            ->join('seasons as s', 's.id', '=', 'ls.season_id')
            ->leftJoin('countries as c', 'c.id', '=', 'l.country_id')
            ->where('ls.id', $leagueSeasonId)
            ->select([
                'ls.id as league_season_id',
                'l.id as league_id',
                'l.name as league_name',
                'c.name as country_name',
                's.id as season_id',
                's.season_key',
                's.label as season_label',
                'ls.is_current',
            ])
            ->first();

        if ($row === null) {
            throw new \RuntimeException('La lega-stagione selezionata non esiste.');
        }

        return (array) $row;
    }

    /** @return Collection<int,object> */
    private function targetTeams(int $leagueSeasonId): Collection
    {
        return DB::table('league_season_teams as lst')
            ->join('teams as t', 't.id', '=', 'lst.team_id')
            ->where('lst.league_season_id', $leagueSeasonId)
            ->where('lst.is_active', true)
            ->select([
                'lst.id as league_season_team_id',
                'lst.tier_stagionale',
                'lst.tier_score',
                't.id as team_id',
                't.name as team_name',
                't.tier_globale',
                't.posizione_media_storica',
            ])
            ->orderBy('t.name')
            ->get();
    }

    /** @return list<int> */
    private function lookbackSeasonKeys(int $targetSeasonKey): array
    {
        $maxLookback = max((int) $this->setting('lookback', 'historical'), (int) $this->setting('lookback', 'momentum'));

        return DB::table('seasons')
            ->where('season_key', '<', $targetSeasonKey)
            ->orderByDesc('season_key')
            ->limit($maxLookback)
            ->pluck('season_key')
            ->map(fn ($seasonKey): int => (int) $seasonKey)
            ->all();
    }

    /** @param list<int> $seasonKeys @return array<string,mixed> */
    private function scoreTeam(object $team, int $leagueSeasonId, array $seasonKeys, string $targetLeagueName): array
    {
        $standings = $this->historicalStandings((int) $team->team_id, $seasonKeys);
        $historicalScore = 0.0;
        $momentumScore = 0.0;
        $details = [];

        $historicalLookback = (int) $this->setting('lookback', 'historical');
        $momentumLookback = (int) $this->setting('lookback', 'momentum');
        $historicalWeights = (array) $this->setting('weights', 'historical');
        $momentumWeights = (array) $this->setting('weights', 'momentum');
        $fusionWeights = (array) $this->setting('weights', 'fusion');

        foreach ($seasonKeys as $index => $seasonKey) {
            $standing = $standings->get($seasonKey);
            $seasonScore = $this->seasonScore($standing);

            if ($index < $historicalLookback) {
                $historicalScore += $seasonScore * (float) ($historicalWeights[$index] ?? 1);
            }

            if ($index < $momentumLookback) {
                $momentumScore += $seasonScore * (float) ($momentumWeights[$index] ?? 1);
            }

            $details[] = [
                'season_key' => $seasonKey,
                'league_name' => $standing?->league_name,
                'score' => round($seasonScore, 4),
                'position' => $standing?->position,
                'points' => $standing?->points,
            ];
        }

        $historicalComponent = $historicalScore / (float) $this->setting('divisors', 'historical');
        $momentumComponent = $momentumScore / (float) $this->setting('divisors', 'momentum');
        $score = ($historicalComponent * (float) ($fusionWeights['historical'] ?? 0))
            + ($momentumComponent * (float) ($fusionWeights['momentum'] ?? 0));

        $trendPenalty = $this->trendPenalty($standings, $seasonKeys);
        $transitionPenalty = $this->transitionPenalty($standings, $seasonKeys, $targetLeagueName);
        $volatilityPenalty = $this->volatilityPenalty($standings, $seasonKeys);
        $score *= $trendPenalty * $transitionPenalty * $volatilityPenalty;
        $score = round($score, 4);
        $tier = $this->tierForScore($score);
        $action = $this->actionFor($team, $tier, $score);

        return [
            'action' => $action,
            'league_season_team_id' => (int) $team->league_season_team_id,
            'team_id' => (int) $team->team_id,
            'team_name' => $team->team_name,
            'tier' => $tier,
            'previous_tier' => $team->tier_globale === null ? null : (int) $team->tier_globale,
            'seasonal_tier' => $team->tier_stagionale === null ? null : (int) $team->tier_stagionale,
            'score' => $score,
            'historical_component' => round($historicalComponent, 4),
            'momentum_component' => round($momentumComponent, 4),
            'trend_penalty' => $trendPenalty,
            'transition_penalty' => $transitionPenalty,
            'volatility_penalty' => $volatilityPenalty,
            'details' => $details,
            'missing_seasons' => collect($details)->whereNull('league_name')->count(),
        ];
    }

    /** @param list<int> $seasonKeys @return Collection<int,object> */
    private function historicalStandings(int $teamId, array $seasonKeys): Collection
    {
        return DB::table('league_season_team_standings as st')
            ->join('league_season_teams as lst', 'lst.id', '=', 'st.league_season_team_id')
            ->join('league_seasons as ls', 'ls.id', '=', 'lst.league_season_id')
            ->join('seasons as s', 's.id', '=', 'ls.season_id')
            ->join('leagues as l', 'l.id', '=', 'ls.league_id')
            ->where('lst.team_id', $teamId)
            ->whereIn('s.season_key', $seasonKeys)
            ->select([
                's.season_key',
                'l.name as league_name',
                'st.position',
                'st.played_games',
                'st.points',
                'st.goals_for',
                'st.goals_against',
                'st.goal_difference',
                'st.stage_name',
                'st.group_name',
            ])
            ->selectSub(function ($query): void {
                $query->from('league_season_teams as size_lst')
                    ->selectRaw('count(*)')
                    ->whereColumn('size_lst.league_season_id', 'ls.id')
                    ->where('size_lst.is_active', true);
            }, 'competition_size')
            ->orderByRaw('case when st.stage_name is null then 0 else 1 end')
            ->orderByRaw('case when st.group_name is null then 0 else 1 end')
            ->get()
            ->unique('season_key')
            ->keyBy(fn (object $row): int => (int) $row->season_key);
    }

    private function seasonScore(?object $standing): float
    {
        if ($standing === null) {
            return (float) $this->setting('rules', 'missing_season_score');
        }

        $played = max(1, (int) ($standing->played_games ?? 38));
        $maxPoints = $played * 3;
        $points = (float) ($standing->points ?? 0);
        $goalsFor = (float) ($standing->goals_for ?? 0);
        $goalsAgainst = (float) ($standing->goals_against ?? 0);
        $position = max(1, (int) ($standing->position ?? 1));
        $competitionSize = max($position, (int) ($standing->competition_size ?? $position));

        $pointsComponent = (1.0 - min(1.0, $points / $maxPoints)) * 20.0;
        $goalsForComponent = (1.0 - min(1.0, $goalsFor / 90.0)) * 20.0;
        $goalsAgainstComponent = min(1.0, $goalsAgainst / 75.0) * 20.0;
        $positionComponent = $competitionSize > 1
            ? (($position - 1) / ($competitionSize - 1)) * 20.0
            : 0.0;
        $multipliers = $this->leagueMultipliers((string) $standing->league_name);
        $metricWeights = (array) $this->setting('weights', 'metrics');
        $goalDifferenceWeight = (float) ($metricWeights['goal_difference'] ?? 0);
        $goalDifferenceComponent = 0.0;

        if ($goalDifferenceWeight > 0) {
            $normalization = (array) $this->setting('normalization', 'goal_difference_per_game');
            $minimum = (float) $normalization['min'];
            $maximum = (float) $normalization['max'];
            if ($maximum <= $minimum) {
                throw new \RuntimeException('Normalizzazione differenza reti non valida.');
            }

            $goalDifferencePerGame = ($goalsFor - $goalsAgainst) / $played;
            $goalDifferenceStrength = min(1.0, max(
                0.0,
                ($goalDifferencePerGame - $minimum) / ($maximum - $minimum)
            ));
            $goalDifferenceComponent = (1.0 - $goalDifferenceStrength) * 20.0;
        }

        return min(20.0, ($pointsComponent * $multipliers['points'] * (float) ($metricWeights['points'] ?? 0))
            + ($goalsForComponent * $multipliers['goals_for'] * (float) ($metricWeights['goals_for'] ?? 0))
            + ($goalsAgainstComponent * $multipliers['goals_against'] * (float) ($metricWeights['goals_against'] ?? 0))
            + ($positionComponent * (float) ($metricWeights['position'] ?? 0))
            + ($goalDifferenceComponent * $goalDifferenceWeight));
    }

    /** @return array{points:float,goals_for:float,goals_against:float} */
    private function leagueMultipliers(string $leagueName): array
    {
        $multipliers = (array) ($this->setting('league_multipliers', $leagueName, false)
            ?? $this->setting('league_multipliers', 'default'));

        return [
            'points' => (float) ($multipliers['points'] ?? 1.60),
            'goals_for' => (float) ($multipliers['goals_for'] ?? 1.60),
            'goals_against' => (float) ($multipliers['goals_against'] ?? 1.00),
        ];
    }

    /** @param Collection<int,object> $standings @param list<int> $seasonKeys */
    private function trendPenalty(Collection $standings, array $seasonKeys): float
    {
        $positions = collect(array_slice($seasonKeys, 0, 3))
            ->map(fn (int $seasonKey) => $standings->get($seasonKey))
            ->filter(fn (?object $standing): bool => $standing !== null && $standing->league_name === 'Serie A' && (int) $standing->position > 0)
            ->map(fn (object $standing): int => (int) $standing->position)
            ->values();

        if ($positions->count() >= 3 && $positions[0] > $positions[1] && $positions[1] > $positions[2]) {
            return (float) $this->setting('rules', 'trend_penalty');
        }

        return 1.0;
    }

    /** @param Collection<int,object> $standings @param list<int> $seasonKeys */
    private function transitionPenalty(Collection $standings, array $seasonKeys, string $targetLeagueName): float
    {
        $latestStanding = $standings->get($seasonKeys[0] ?? null);

        if ($latestStanding === null || (string) $latestStanding->league_name === $targetLeagueName) {
            return 1.0;
        }

        $dynamic = (array) ($this->setting('transition_penalties', 'promoted_dynamic', false) ?? []);
        if ((bool) ($dynamic['enabled'] ?? false)) {
            $minimum = (float) ($dynamic['min'] ?? 1.0);
            $maximum = (float) ($dynamic['max'] ?? $minimum);
            $strength = $this->relativeStrength(
                (int) $latestStanding->position,
                (int) $latestStanding->competition_size
            );

            return round($minimum + ((1.0 - $strength) * ($maximum - $minimum)), 4);
        }

        $penalties = (array) $this->setting('transition_penalties', 'by_case');

        return (float) ($penalties['promoted_from_lower_league'] ?? 1.0);
    }

    /** @param Collection<int,object> $standings @param list<int> $seasonKeys */
    private function volatilityPenalty(Collection $standings, array $seasonKeys): float
    {
        $factor = (float) ($this->setting('rules', 'volatility_penalty_factor', false) ?? 0);
        if ($factor <= 0) {
            return 1.0;
        }

        $strengths = collect($seasonKeys)
            ->map(fn (int $seasonKey): ?object => $standings->get($seasonKey))
            ->filter()
            ->map(fn (object $standing): float => $this->relativeStrength(
                (int) $standing->position,
                (int) $standing->competition_size
            ))
            ->values();

        if ($strengths->count() < 2) {
            return 1.0;
        }

        $mean = (float) $strengths->avg();
        $volatility = sqrt((float) $strengths->avg(
            fn (float $strength): float => ($strength - $mean) ** 2
        ));

        return round(1.0 + ($volatility * $factor), 4);
    }

    private function relativeStrength(int $position, int $competitionSize): float
    {
        if ($competitionSize <= 1) {
            return 1.0;
        }

        return 1.0 - (($position - 1) / ($competitionSize - 1));
    }

    private function tierForScore(float $score): int
    {
        foreach ((array) $this->setting('thresholds', 'by_tier') as $tier => $maxScore) {
            if ($score <= (float) $maxScore) {
                return (int) $tier;
            }
        }

        return 5;
    }

    private function performanceStatus(?int $expectedTier, ?int $actualTier, ?float $scoreDelta): string
    {
        if ($expectedTier === null || $scoreDelta === null) {
            return 'missing_tier';
        }

        if ($actualTier === null) {
            return 'missing_standing';
        }

        if ($actualTier < $expectedTier) {
            return 'overperformed';
        }

        if ($actualTier > $expectedTier) {
            return 'underperformed';
        }

        if (abs($scoreDelta) >= 2.5) {
            return $scoreDelta < 0 ? 'overperformed' : 'underperformed';
        }

        return 'aligned';
    }

    /** @param list<array<string,mixed>> $rows */
    private function performanceAuditStatus(array $rows): string
    {
        if ($rows === []) {
            return 'missing_teams';
        }

        if (collect($rows)->contains(fn (array $row): bool => in_array($row['status'], ['missing_tier', 'missing_standing'], true))) {
            return 'attention_required';
        }

        return 'ready';
    }

    /** @param Collection<int,array<string,mixed>> $rows @return array<string,int|float> */
    private function rankingMetrics(Collection $rows): array
    {
        $teams = $rows->count();
        if ($teams < 2) {
            return [
                'teams' => $teams,
                'ranking_accuracy_pct' => 0.0,
                'position_mae' => 0.0,
                'exact_tier_pct' => 0.0,
                'within_one_tier_pct' => 0.0,
            ];
        }

        $squaredDistance = $rows->sum(
            fn (array $row): int => ((int) $row['predicted_position'] - (int) $row['actual_position']) ** 2
        );
        $spearman = 1.0 - ((6.0 * $squaredDistance) / ($teams * (($teams ** 2) - 1)));
        $withActualTier = $rows->whereNotNull('actual_tier');
        $tierRows = max(1, $withActualTier->count());

        return [
            'teams' => $teams,
            'ranking_accuracy_pct' => round(max(0.0, $spearman) * 100.0, 2),
            'position_mae' => round((float) $rows->avg(
                fn (array $row): int => abs((int) $row['predicted_position'] - (int) $row['actual_position'])
            ), 2),
            'exact_tier_pct' => round(
                ($withActualTier->filter(fn (array $row): bool => $row['predicted_tier'] === $row['actual_tier'])->count() / $tierRows) * 100.0,
                2
            ),
            'within_one_tier_pct' => round(
                ($withActualTier->filter(
                    fn (array $row): bool => abs((int) $row['predicted_tier'] - (int) $row['actual_tier']) <= 1
                )->count() / $tierRows) * 100.0,
                2
            ),
        ];
    }

    private function setting(string $group, string $key, bool $required = true): mixed
    {
        if (array_key_exists($group, $this->settingOverrides)
            && array_key_exists($key, $this->settingOverrides[$group])) {
            return $this->settingOverrides[$group][$key];
        }

        $settings = $this->settings();
        if (array_key_exists($group, $settings) && array_key_exists($key, $settings[$group])) {
            return $settings[$group][$key];
        }

        if ($required) {
            throw new \RuntimeException("Impostazione tier mancante: {$group}.{$key}");
        }

        return null;
    }

    /** @return array<string,array<string,mixed>> */
    private function settings(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $this->settings = DB::table('team_tier_settings')
            ->get(['setting_group', 'setting_key', 'value'])
            ->groupBy('setting_group')
            ->map(fn (Collection $rows): array => $rows
                ->mapWithKeys(fn (object $row): array => [
                    $row->setting_key => json_decode((string) $row->value, true, flags: JSON_THROW_ON_ERROR),
                ])
                ->all())
            ->all();

        return $this->settings;
    }

    private function actionFor(object $team, int $tier, float $score): string
    {
        $sameSeason = (int) ($team->tier_stagionale ?? 0) === $tier
            && round((float) ($team->tier_score ?? -1), 4) === $score;

        if ($team->tier_stagionale === null && $team->tier_score === null) {
            return 'CREATE';
        }

        return $sameSeason ? 'UNCHANGED' : 'UPDATE';
    }

    /** @param list<array<string,mixed>> $rows */
    private function statusForRows(array $rows): string
    {
        if ($rows === []) {
            return 'missing_teams';
        }

        return collect($rows)->contains(fn (array $row): bool => in_array($row['action'], ['CREATE', 'UPDATE'], true))
            ? 'changes_pending'
            : 'unchanged';
    }
}
