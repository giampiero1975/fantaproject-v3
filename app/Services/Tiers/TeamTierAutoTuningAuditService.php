<?php

namespace App\Services\Tiers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TeamTierAutoTuningAuditService
{
    /** @param list<int> $leagueSeasonIds @return array<string,mixed> */
    public function analyze(array $leagueSeasonIds): array
    {
        $guards = (array) $this->setting('auto_tuning_guards', 'acceptance');
        $profiles = [
            $this->baselineProfile(),
            $this->activeProfile(),
            ...$this->incrementalProfiles(),
        ];

        $evaluated = collect($profiles)
            ->map(function (array $profile) use ($leagueSeasonIds): array {
                $report = (new TeamTieringService($profile['overrides']))
                    ->walkForwardAudit($leagueSeasonIds);

                return $profile + [
                    'summary' => $report['summary'],
                    'seasons' => collect($report['seasons'])->map(fn (array $season): array => [
                        'league_season_id' => $season['league_season']['league_season_id'],
                        'season_label' => $season['league_season']['season_label'],
                        ...$season['metrics'],
                    ])->all(),
                ];
            })
            ->values();

        $evaluatedByKey = $evaluated->keyBy('profile_key');
        $candidates = $evaluated
            ->map(function (array $candidate) use ($evaluatedByKey, $guards): array {
                $reference = $evaluatedByKey->get($candidate['reference_profile_key']);
                if ($reference === null) {
                    throw new \RuntimeException("Profilo di riferimento mancante: {$candidate['reference_profile_key']}");
                }

                return $this->compareWithReference($candidate, $reference, $guards);
            })
            ->all();
        $active = collect($candidates)->firstWhere('is_active_profile', true);
        $acceptedIncremental = collect($candidates)
            ->filter(fn (array $candidate): bool => str_starts_with($candidate['profile_key'], 'incremental_'))
            ->where('accepted', true)
            ->count();

        return [
            'status' => ! ($active['accepted'] ?? false)
                ? 'rejected'
                : ($acceptedIncremental > 0 ? 'validated_incremental_candidate_found' : 'validated_no_incremental_candidate'),
            'guards' => $guards,
            'source_signal_audit_run_id' => DB::table('ai_team_tier_audit_runs')->latest('id')->value('id'),
            'accepted_incremental_candidates' => $acceptedIncremental,
            'candidates' => $candidates,
        ];
    }

    /** @param array<string,mixed> $report @return array<string,mixed> */
    public function persist(array $report): array
    {
        return DB::transaction(function () use ($report): array {
            $now = now();
            $uuid = (string) Str::uuid();
            $runId = DB::table('ai_team_tier_tuning_runs')->insertGetId([
                'uuid' => $uuid,
                'ai_team_tier_audit_run_id' => $report['source_signal_audit_run_id'],
                'seasons_count' => count($report['candidates'][0]['seasons'] ?? []),
                'validation_status' => $report['status'],
                'min_average_ranking_uplift_pct' => $report['guards']['min_average_ranking_uplift_pct'],
                'max_single_season_ranking_drop_pct' => $report['guards']['max_single_season_ranking_drop_pct'],
                'max_position_mae_increase' => $report['guards']['max_position_mae_increase'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($report['candidates'] as $candidate) {
                $parameters = $candidate['parameters'];
                $candidateId = DB::table('ai_team_tier_tuning_candidates')->insertGetId([
                    'ai_team_tier_tuning_run_id' => $runId,
                    'profile_key' => $candidate['profile_key'],
                    'reference_profile_key' => $candidate['reference_profile_key'],
                    'label' => $candidate['label'],
                    'is_active_profile' => $candidate['is_active_profile'],
                    'points_weight' => $parameters['points_weight'],
                    'goals_for_weight' => $parameters['goals_for_weight'],
                    'goals_against_weight' => $parameters['goals_against_weight'],
                    'goal_difference_weight' => $parameters['goal_difference_weight'],
                    'position_weight' => $parameters['position_weight'],
                    'historical_weight' => $parameters['historical_weight'],
                    'momentum_weight' => $parameters['momentum_weight'],
                    'promoted_penalty' => $parameters['promoted_penalty'],
                    'promoted_dynamic_enabled' => $parameters['promoted_dynamic_enabled'],
                    'promoted_penalty_min' => $parameters['promoted_penalty_min'],
                    'promoted_penalty_max' => $parameters['promoted_penalty_max'],
                    'volatility_penalty_factor' => $parameters['volatility_penalty_factor'],
                    'ranking_accuracy_pct' => $candidate['summary']['ranking_accuracy_pct'],
                    'position_mae' => $candidate['summary']['position_mae'],
                    'exact_tier_pct' => $candidate['summary']['exact_tier_pct'],
                    'within_one_tier_pct' => $candidate['summary']['within_one_tier_pct'],
                    'ranking_uplift_pct' => $candidate['comparison']['ranking_uplift_pct'],
                    'position_mae_delta' => $candidate['comparison']['position_mae_delta'],
                    'accepted' => $candidate['accepted'],
                    'rejection_reason' => $candidate['rejection_reason'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('ai_team_tier_tuning_candidate_seasons')->insert(
                    collect($candidate['seasons'])->map(fn (array $season): array => [
                        'ai_team_tier_tuning_candidate_id' => $candidateId,
                        'league_season_id' => $season['league_season_id'],
                        'ranking_accuracy_pct' => $season['ranking_accuracy_pct'],
                        'position_mae' => $season['position_mae'],
                        'exact_tier_pct' => $season['exact_tier_pct'],
                        'within_one_tier_pct' => $season['within_one_tier_pct'],
                        'ranking_uplift_pct' => $season['ranking_uplift_pct'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            }

            $report['audit_run'] = [
                'id' => $runId,
                'uuid' => $uuid,
            ];

            return $report;
        });
    }

    /** @return array<string,mixed> */
    private function baselineProfile(): array
    {
        $overrides = (array) $this->setting('auto_tuning_profiles', 'legacy_baseline');

        return $this->profile(
            'legacy_baseline',
            'Baseline legacy',
            false,
            $overrides,
            'legacy_baseline'
        );
    }

    /** @return array<string,mixed> */
    private function activeProfile(): array
    {
        $overrides = [
            'weights' => [
                'metrics' => $this->setting('weights', 'metrics'),
                'fusion' => $this->setting('weights', 'fusion'),
            ],
            'transition_penalties' => [
                'by_case' => $this->setting('transition_penalties', 'by_case'),
            ],
        ];

        return $this->profile('active_profile', 'Profilo attivo DB', true, $overrides, 'legacy_baseline');
    }

    /** @return list<array<string,mixed>> */
    private function incrementalProfiles(): array
    {
        $grid = (array) $this->setting('auto_tuning_experiments', 'incremental_grid');
        $activeOverrides = $this->activeProfile()['overrides'];
        $profiles = [];

        foreach ((array) $grid['metric_profiles'] as $metricKey => $metrics) {
            foreach ((array) $grid['promotion_profiles'] as $promotionKey => $promotion) {
                foreach ((array) $grid['volatility_factors'] as $volatilityFactor) {
                    $volatilityKey = str_replace('.', '_', (string) $volatilityFactor);
                    $key = "incremental_{$metricKey}_{$promotionKey}_v{$volatilityKey}";
                    $overrides = $activeOverrides;
                    $overrides['weights']['metrics'] = $metrics;
                    $overrides['transition_penalties']['promoted_dynamic'] = $promotion;
                    $overrides['rules']['volatility_penalty_factor'] = (float) $volatilityFactor;
                    $profiles[] = $this->profile(
                        $key,
                        "{$metricKey} · {$promotionKey} · volatility {$volatilityFactor}",
                        false,
                        $overrides,
                        'active_profile'
                    );
                }
            }
        }

        return $profiles;
    }

    /** @param array<string,array<string,mixed>> $overrides @return array<string,mixed> */
    private function profile(
        string $key,
        string $label,
        bool $active,
        array $overrides,
        string $referenceProfileKey
    ): array
    {
        $metrics = (array) $overrides['weights']['metrics'];
        $fusion = (array) $overrides['weights']['fusion'];
        $transitions = (array) $overrides['transition_penalties']['by_case'];
        $dynamic = (array) ($overrides['transition_penalties']['promoted_dynamic'] ?? []);

        return [
            'profile_key' => $key,
            'reference_profile_key' => $referenceProfileKey,
            'label' => $label,
            'is_active_profile' => $active,
            'overrides' => $overrides,
            'parameters' => [
                'points_weight' => (float) ($metrics['points'] ?? 0),
                'goals_for_weight' => (float) ($metrics['goals_for'] ?? 0),
                'goals_against_weight' => (float) ($metrics['goals_against'] ?? 0),
                'goal_difference_weight' => (float) ($metrics['goal_difference'] ?? 0),
                'position_weight' => (float) ($metrics['position'] ?? 0),
                'historical_weight' => (float) ($fusion['historical'] ?? 0),
                'momentum_weight' => (float) ($fusion['momentum'] ?? 0),
                'promoted_penalty' => (float) ($transitions['promoted_from_lower_league'] ?? 1),
                'promoted_dynamic_enabled' => (bool) ($dynamic['enabled'] ?? false),
                'promoted_penalty_min' => isset($dynamic['min']) ? (float) $dynamic['min'] : null,
                'promoted_penalty_max' => isset($dynamic['max']) ? (float) $dynamic['max'] : null,
                'volatility_penalty_factor' => (float) ($overrides['rules']['volatility_penalty_factor'] ?? 0),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $candidate
     * @param array<string,mixed> $baseline
     * @param array<string,mixed> $guards
     * @return array<string,mixed>
     */
    private function compareWithReference(array $candidate, array $reference, array $guards): array
    {
        $referenceSeasons = collect($reference['seasons'])->keyBy('league_season_id');
        $seasons = collect($candidate['seasons'])
            ->map(function (array $season) use ($referenceSeasons): array {
                $referenceSeason = $referenceSeasons->get($season['league_season_id']);

                return $season + [
                    'ranking_uplift_pct' => round(
                        $season['ranking_accuracy_pct'] - $referenceSeason['ranking_accuracy_pct'],
                        3
                    ),
                ];
            })
            ->all();
        $rankingUplift = round(
            $candidate['summary']['ranking_accuracy_pct'] - $reference['summary']['ranking_accuracy_pct'],
            3
        );
        $maeDelta = round(
            $candidate['summary']['position_mae'] - $reference['summary']['position_mae'],
            3
        );

        if ($candidate['profile_key'] === $candidate['reference_profile_key']) {
            return array_merge($candidate, [
                'seasons' => $seasons,
                'comparison' => [
                    'ranking_uplift_pct' => 0.0,
                    'position_mae_delta' => 0.0,
                ],
                'accepted' => true,
                'rejection_reason' => null,
            ]);
        }

        $reasons = [];
        if ($rankingUplift < (float) $guards['min_average_ranking_uplift_pct']) {
            $reasons[] = 'uplift medio inferiore al minimo';
        }

        $maxDrop = (float) $guards['max_single_season_ranking_drop_pct'];
        if (collect($seasons)->contains(fn (array $season): bool => $season['ranking_uplift_pct'] < -$maxDrop)) {
            $reasons[] = 'peggioramento oltre soglia in almeno una stagione';
        }

        if ($maeDelta > (float) $guards['max_position_mae_increase']) {
            $reasons[] = 'aumento MAE oltre soglia';
        }

        return array_merge($candidate, [
            'seasons' => $seasons,
            'comparison' => [
                'ranking_uplift_pct' => $rankingUplift,
                'position_mae_delta' => $maeDelta,
            ],
            'accepted' => $reasons === [],
            'rejection_reason' => $reasons === [] ? null : implode('; ', $reasons),
        ]);
    }

    private function setting(string $group, string $key): mixed
    {
        $row = DB::table('team_tier_settings')
            ->where('setting_group', $group)
            ->where('setting_key', $key)
            ->first(['value']);

        if ($row === null) {
            throw new \RuntimeException("Impostazione tier mancante: {$group}.{$key}");
        }

        return json_decode((string) $row->value, true, flags: JSON_THROW_ON_ERROR);
    }
}
