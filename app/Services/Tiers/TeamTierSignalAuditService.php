<?php

namespace App\Services\Tiers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TeamTierSignalAuditService
{
    public function __construct(private readonly TeamTieringService $tieringService)
    {
    }

    /**
     * Builds prospective observations: every feature comes from seasons
     * preceding the target season, while reality is used only as outcome.
     *
     * @param list<int> $leagueSeasonIds
     * @return array<string,mixed>
     */
    public function analyze(array $leagueSeasonIds): array
    {
        $historyRequested = $this->historyRequested();
        $walkForward = $this->tieringService->walkForwardAudit($leagueSeasonIds);
        $observations = collect($leagueSeasonIds)
            ->flatMap(fn (int $leagueSeasonId): Collection => $this->seasonObservations(
                $leagueSeasonId,
                $historyRequested
            ))
            ->values();

        return [
            'status' => $observations->isNotEmpty() ? 'ready' : 'attention_required',
            'summary' => $walkForward['summary'],
            'signals' => $this->signalMetrics($observations),
            'observations' => $observations->all(),
        ];
    }

    /** @param array<string,mixed> $report @return array<string,mixed> */
    public function persist(array $report): array
    {
        return DB::transaction(function () use ($report): array {
            $uuid = (string) Str::uuid();
            $now = now();
            $runId = DB::table('ai_team_tier_audit_runs')->insertGetId([
                'uuid' => $uuid,
                'seasons_count' => $report['summary']['seasons'],
                'observations_count' => count($report['observations']),
                'ranking_accuracy_pct' => $report['summary']['ranking_accuracy_pct'],
                'position_mae' => $report['summary']['position_mae'],
                'exact_tier_pct' => $report['summary']['exact_tier_pct'],
                'within_one_tier_pct' => $report['summary']['within_one_tier_pct'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (array_chunk($report['observations'], 250) as $chunk) {
                DB::table('ai_team_tier_audit_observations')->insert(
                    collect($chunk)->map(fn (array $row): array => $row + [
                        'ai_team_tier_audit_run_id' => $runId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            }

            DB::table('ai_team_tier_audit_metrics')->insert(
                collect($report['signals'])->map(fn (array $metric): array => $metric + [
                    'ai_team_tier_audit_run_id' => $runId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all()
            );

            $report['audit_run'] = [
                'id' => $runId,
                'uuid' => $uuid,
            ];

            return $report;
        });
    }

    /** @return Collection<int,array<string,mixed>> */
    private function seasonObservations(int $leagueSeasonId, int $historyRequested): Collection
    {
        $analysis = $this->tieringService->analyze($leagueSeasonId);
        $actualByTeam = collect($this->tieringService->performanceAudit($leagueSeasonId)['rows'])
            ->keyBy('team_name');
        $targetSize = count($analysis['rows']);
        $targetSeasonKey = (int) $analysis['league_season']['season_key'];
        $targetLeagueName = (string) $analysis['league_season']['league_name'];
        $predictedPosition = 0;

        return collect($analysis['rows'])
            ->values()
            ->map(function (array $prediction) use (
                &$predictedPosition,
                $actualByTeam,
                $historyRequested,
                $leagueSeasonId,
                $targetLeagueName,
                $targetSeasonKey,
                $targetSize
            ): ?array {
                $predictedPosition++;
                $actual = $actualByTeam->get($prediction['team_name']);
                if ($actual === null || $actual['position'] === null) {
                    return null;
                }

                $history = $this->teamHistory(
                    (int) $prediction['team_id'],
                    $targetSeasonKey,
                    $historyRequested
                );
                $features = $this->historyFeatures($history, $historyRequested, $targetLeagueName);
                $actualPosition = (int) $actual['position'];

                return [
                    'league_season_id' => $leagueSeasonId,
                    'team_id' => (int) $prediction['team_id'],
                    'target_season_key' => $targetSeasonKey,
                    'predicted_position' => $predictedPosition,
                    'actual_position' => $actualPosition,
                    'predicted_tier' => (int) $prediction['tier'],
                    'actual_tier' => $actual['actual_tier'] === null ? null : (int) $actual['actual_tier'],
                    'predicted_score' => (float) $prediction['score'],
                    'actual_score' => $actual['actual_score'] === null ? null : (float) $actual['actual_score'],
                    'position_error' => $predictedPosition - $actualPosition,
                    'absolute_position_error' => abs($predictedPosition - $actualPosition),
                    ...$features,
                    'actual_relative_strength' => $this->relativeStrength($actualPosition, $targetSize),
                ];
            })
            ->filter()
            ->values();
    }

    /** @return Collection<int,object> */
    private function teamHistory(int $teamId, int $targetSeasonKey, int $historyRequested): Collection
    {
        return DB::table('league_season_team_standings as st')
            ->join('league_season_teams as lst', 'lst.id', '=', 'st.league_season_team_id')
            ->join('league_seasons as ls', 'ls.id', '=', 'lst.league_season_id')
            ->join('seasons as s', 's.id', '=', 'ls.season_id')
            ->join('leagues as l', 'l.id', '=', 'ls.league_id')
            ->where('lst.team_id', $teamId)
            ->where('s.season_key', '<', $targetSeasonKey)
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
            ->orderByDesc('s.season_key')
            ->orderByRaw('case when st.stage_name is null then 0 else 1 end')
            ->orderByRaw('case when st.group_name is null then 0 else 1 end')
            ->get()
            ->unique('season_key')
            ->take($historyRequested)
            ->values();
    }

    /** @param Collection<int,object> $history @return array<string,mixed> */
    private function historyFeatures(Collection $history, int $historyRequested, string $targetLeagueName): array
    {
        $latest = $history->first();
        $strengths = $history
            ->map(fn (object $standing): float => $this->relativeStrength(
                (int) $standing->position,
                (int) $standing->competition_size
            ))
            ->values();
        $played = $latest === null ? null : max(1, (int) $latest->played_games);
        $latestStrength = $strengths->first();
        $isPromoted = $latest !== null && (string) $latest->league_name !== $targetLeagueName;

        return [
            'history_available' => $history->count(),
            'history_requested' => $historyRequested,
            'history_coverage' => round($history->count() / max(1, $historyRequested), 4),
            'latest_points_per_game' => $latest === null ? null : round((float) $latest->points / $played, 4),
            'latest_goals_for_per_game' => $latest === null ? null : round((float) $latest->goals_for / $played, 4),
            'latest_goals_against_per_game' => $latest === null ? null : round((float) $latest->goals_against / $played, 4),
            'latest_goal_difference_per_game' => $latest === null
                ? null
                : round(((float) $latest->goals_for - (float) $latest->goals_against) / $played, 4),
            'latest_relative_strength' => $latestStrength,
            'trend_slope' => $this->trendSlope($strengths->reverse()->values()),
            'volatility' => $this->standardDeviation($strengths),
            'regression_gap' => $latestStrength === null
                ? null
                : round($latestStrength - (float) $strengths->avg(), 5),
            'is_promoted' => $isPromoted,
            'lower_league_relative_strength' => $isPromoted ? $latestStrength : null,
        ];
    }

    /** @param Collection<int,array<string,mixed>> $observations @return list<array<string,mixed>> */
    private function signalMetrics(Collection $observations): array
    {
        $signals = [
            'predicted_score' => 'Score tier attuale',
            'history_coverage' => 'Copertura storico',
            'latest_points_per_game' => 'Punti per partita',
            'latest_goals_for_per_game' => 'Gol fatti per partita',
            'latest_goals_against_per_game' => 'Gol subiti per partita',
            'latest_goal_difference_per_game' => 'Differenza reti per partita',
            'latest_relative_strength' => 'Forza relativa ultima stagione',
            'trend_slope' => 'Pendenza rendimento',
            'volatility' => 'Volatilita storica',
            'regression_gap' => 'Distanza dalla media storica',
            'is_promoted' => 'Neopromossa',
            'lower_league_relative_strength' => 'Forza relativa lega inferiore',
        ];

        return collect($signals)
            ->map(function (string $label, string $signalKey) use ($observations): array {
                $pairs = $observations
                    ->filter(fn (array $row): bool => $row[$signalKey] !== null)
                    ->map(fn (array $row): array => [
                        (float) $row[$signalKey],
                        (float) $row['actual_relative_strength'],
                    ])
                    ->values();
                $correlation = $this->pearsonCorrelation($pairs);

                return [
                    'signal_key' => $signalKey,
                    'label' => $label,
                    'sample_count' => $pairs->count(),
                    'pearson_correlation' => $correlation,
                    'absolute_correlation' => $correlation === null ? null : abs($correlation),
                ];
            })
            ->sortByDesc(fn (array $metric): float => (float) ($metric['absolute_correlation'] ?? -1))
            ->values()
            ->all();
    }

    /** @param Collection<int,array{0:float,1:float}> $pairs */
    private function pearsonCorrelation(Collection $pairs): ?float
    {
        if ($pairs->count() < 3) {
            return null;
        }

        $xMean = (float) $pairs->avg(0);
        $yMean = (float) $pairs->avg(1);
        $numerator = $pairs->sum(fn (array $pair): float => ($pair[0] - $xMean) * ($pair[1] - $yMean));
        $xSquare = $pairs->sum(fn (array $pair): float => ($pair[0] - $xMean) ** 2);
        $ySquare = $pairs->sum(fn (array $pair): float => ($pair[1] - $yMean) ** 2);
        $denominator = sqrt($xSquare * $ySquare);

        return $denominator == 0.0 ? null : round($numerator / $denominator, 6);
    }

    /** @param Collection<int,float> $values */
    private function trendSlope(Collection $values): ?float
    {
        $count = $values->count();
        if ($count < 2) {
            return null;
        }

        $xMean = ($count - 1) / 2;
        $yMean = (float) $values->avg();
        $numerator = 0.0;
        $denominator = 0.0;

        foreach ($values->values() as $index => $value) {
            $numerator += ($index - $xMean) * ($value - $yMean);
            $denominator += ($index - $xMean) ** 2;
        }

        return $denominator == 0.0 ? null : round($numerator / $denominator, 5);
    }

    /** @param Collection<int,float> $values */
    private function standardDeviation(Collection $values): ?float
    {
        if ($values->count() < 2) {
            return null;
        }

        $mean = (float) $values->avg();

        return round(sqrt((float) $values->avg(fn (float $value): float => ($value - $mean) ** 2)), 5);
    }

    private function relativeStrength(int $position, int $competitionSize): float
    {
        if ($competitionSize <= 1) {
            return 1.0;
        }

        return round(1.0 - (($position - 1) / ($competitionSize - 1)), 4);
    }

    private function historyRequested(): int
    {
        return max(1, (int) DB::table('team_tier_settings')
            ->where('setting_group', 'lookback')
            ->whereIn('setting_key', ['historical', 'momentum'])
            ->pluck('value')
            ->map(fn (mixed $value): int => (int) json_decode((string) $value, true, flags: JSON_THROW_ON_ERROR))
            ->max());
    }
}
