<?php

namespace Tests\Feature;

use App\Services\Tiers\TeamTieringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class SyncTeamTiersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_calculates_tiers_without_persisting_changes(): void
    {
        $targetLeagueSeasonId = $this->seedTierScenario();

        $this->artisan('team-tiers:sync', [
            '--league-season-id' => $targetLeagueSeasonId,
            '--json' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('team_tier_settings', [
            'setting_group' => 'weights',
            'setting_key' => 'fusion',
        ]);

        $this->assertDatabaseHas('teams', [
            'name' => 'AC Milan',
            'tier_globale' => null,
        ]);

        $logPath = storage_path('logs/administration/tier-squadre/tier-squadre.log');
        $this->assertTrue(File::exists($logPath));
        $this->assertStringContainsString('[tier_squadre][INFO]', File::get($logPath));
    }

    public function test_apply_persists_team_and_seasonal_tiers(): void
    {
        $targetLeagueSeasonId = $this->seedTierScenario();

        $this->artisan('team-tiers:sync', [
            '--league-season-id' => $targetLeagueSeasonId,
            '--apply' => true,
            '--json' => true,
        ])->assertExitCode(0);

        $milan = DB::table('teams')->where('name', 'AC Milan')->first();
        $this->assertNotNull($milan->tier_globale);
        $this->assertNotNull($milan->posizione_media_storica);

        $this->assertDatabaseMissing('league_season_teams', [
            'league_season_id' => $targetLeagueSeasonId,
            'team_id' => $milan->id,
            'tier_stagionale' => null,
        ]);

        $this->assertDatabaseMissing('league_season_teams', [
            'league_season_id' => $targetLeagueSeasonId,
            'team_id' => $milan->id,
            'tier_score' => null,
        ]);
    }

    public function test_apply_historical_season_does_not_overwrite_global_team_score(): void
    {
        $this->seedTierScenario();
        $historicalLeagueSeasonId = DB::table('league_seasons as ls')
            ->join('seasons as s', 's.id', '=', 'ls.season_id')
            ->where('s.season_key', 2025)
            ->value('ls.id');

        DB::table('teams')->where('name', 'AC Milan')->update([
            'tier_globale' => 1,
            'posizione_media_storica' => 6.1234,
        ]);

        $this->artisan('team-tiers:sync', [
            '--league-season-id' => $historicalLeagueSeasonId,
            '--apply' => true,
            '--json' => true,
        ])->assertExitCode(0);

        $milan = DB::table('teams')->where('name', 'AC Milan')->first();
        $this->assertSame(1, (int) $milan->tier_globale);
        $this->assertSame(6.1234, (float) $milan->posizione_media_storica);

        $this->assertNotNull(DB::table('league_season_teams')
            ->where('league_season_id', $historicalLeagueSeasonId)
            ->where('team_id', $milan->id)
            ->value('tier_score'));
    }

    public function test_performance_audit_compares_expected_tier_with_real_standing_score(): void
    {
        $targetLeagueSeasonId = $this->seedTierScenario();

        $this->artisan('team-tiers:sync', [
            '--league-season-id' => $targetLeagueSeasonId,
            '--apply' => true,
            '--json' => true,
        ])->assertExitCode(0);

        $milan = DB::table('teams')->where('name', 'AC Milan')->first();
        $roma = DB::table('teams')->where('name', 'AS Roma')->first();

        foreach ([[$milan->id, 1, 86, 82, 30], [$roma->id, 8, 58, 55, 50]] as [$teamId, $position, $points, $goalsFor, $goalsAgainst]) {
            $leagueSeasonTeamId = DB::table('league_season_teams')
                ->where('league_season_id', $targetLeagueSeasonId)
                ->where('team_id', $teamId)
                ->value('id');

            DB::table('league_season_team_standings')->insert([
                'league_season_team_id' => $leagueSeasonTeamId,
                'position' => $position,
                'played_games' => 38,
                'won' => null,
                'draw' => null,
                'lost' => null,
                'points' => $points,
                'goals_for' => $goalsFor,
                'goals_against' => $goalsAgainst,
                'goal_difference' => $goalsFor - $goalsAgainst,
                'stage_name' => null,
                'group_name' => null,
                'synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->artisan('team-tiers:audit-performance', [
            '--league-season-id' => $targetLeagueSeasonId,
            '--json' => true,
        ])->assertExitCode(0);

        $logPath = storage_path('logs/administration/tier-squadre/tier-squadre.log');
        $this->assertStringContainsString('Team tier performance audit completed.', File::get($logPath));
    }

    public function test_walk_forward_audit_recalculates_historical_season_without_writes(): void
    {
        $this->seedTierScenario();
        $historicalLeagueSeasonId = DB::table('league_seasons as ls')
            ->join('seasons as s', 's.id', '=', 'ls.season_id')
            ->where('s.season_key', 2025)
            ->value('ls.id');

        $this->artisan('team-tiers:audit-walk-forward', [
            '--league-season-id' => [$historicalLeagueSeasonId],
            '--json' => true,
        ])->assertExitCode(0);

        $report = (new TeamTieringService())->walkForwardAudit([(int) $historicalLeagueSeasonId]);

        $this->assertSame('ready', $report['status']);
        $this->assertSame(1, $report['summary']['seasons']);
        $this->assertSame(2, $report['summary']['teams']);
        $this->assertDatabaseMissing('league_season_teams', [
            'league_season_id' => $historicalLeagueSeasonId,
            'tier_stagionale' => 1,
        ]);

        $logPath = storage_path('logs/administration/tier-squadre/tier-squadre.log');
        $this->assertStringContainsString('Team tier walk-forward audit completed.', File::get($logPath));
    }

    public function test_signal_audit_persists_structured_ai_dataset_only_when_requested(): void
    {
        $this->seedTierScenario();
        $historicalLeagueSeasonId = (int) DB::table('league_seasons as ls')
            ->join('seasons as s', 's.id', '=', 'ls.season_id')
            ->where('s.season_key', 2025)
            ->value('ls.id');

        $this->artisan('team-tiers:audit-signals', [
            '--league-season-id' => [$historicalLeagueSeasonId],
        ])->assertExitCode(0);

        $this->assertSame(0, DB::table('ai_team_tier_audit_runs')->count());

        $this->artisan('team-tiers:audit-signals', [
            '--league-season-id' => [$historicalLeagueSeasonId],
            '--persist' => true,
            '--json' => true,
        ])->assertExitCode(0);

        $runId = DB::table('ai_team_tier_audit_runs')->value('id');
        $this->assertNotNull($runId);
        $this->assertSame(2, DB::table('ai_team_tier_audit_observations')
            ->where('ai_team_tier_audit_run_id', $runId)
            ->count());
        $this->assertDatabaseHas('ai_team_tier_audit_metrics', [
            'ai_team_tier_audit_run_id' => $runId,
            'signal_key' => 'latest_points_per_game',
        ]);
        $this->assertDatabaseHas('ai_team_tier_audit_metrics', [
            'ai_team_tier_audit_run_id' => $runId,
            'signal_key' => 'volatility',
        ]);

        $logPath = storage_path('logs/administration/tier-squadre/tier-squadre.log');
        $this->assertStringContainsString('[tier_squadre][audit_signals][INFO]', File::get($logPath));
    }

    public function test_auto_tuning_audit_compares_db_profiles_and_persists_reproducible_results(): void
    {
        $this->seedTierScenario();
        DB::table('team_tier_settings')
            ->where('setting_group', 'auto_tuning_experiments')
            ->where('setting_key', 'incremental_grid')
            ->update([
                'value' => json_encode([
                    'metric_profiles' => [
                        'gd00' => [
                            'points' => 0.48,
                            'goals_for' => 0.34,
                            'goals_against' => 0.18,
                            'goal_difference' => 0.0,
                        ],
                    ],
                    'promotion_profiles' => [
                        'fixed' => ['enabled' => false],
                    ],
                    'volatility_factors' => [0.0],
                ]),
            ]);
        $historicalLeagueSeasonId = (int) DB::table('league_seasons as ls')
            ->join('seasons as s', 's.id', '=', 'ls.season_id')
            ->where('s.season_key', 2025)
            ->value('ls.id');

        $this->artisan('team-tiers:audit-auto-tuning', [
            '--league-season-id' => [$historicalLeagueSeasonId],
        ])->assertExitCode(0);

        $this->assertSame(0, DB::table('ai_team_tier_tuning_runs')->count());

        $this->artisan('team-tiers:audit-auto-tuning', [
            '--league-season-id' => [$historicalLeagueSeasonId],
            '--persist' => true,
            '--json' => true,
        ])->assertExitCode(0);

        $runId = DB::table('ai_team_tier_tuning_runs')->value('id');
        $this->assertNotNull($runId);
        $this->assertSame(3, DB::table('ai_team_tier_tuning_candidates')
            ->where('ai_team_tier_tuning_run_id', $runId)
            ->count());
        $this->assertSame(3, DB::table('ai_team_tier_tuning_candidate_seasons')->count());
        $this->assertDatabaseHas('ai_team_tier_tuning_candidates', [
            'ai_team_tier_tuning_run_id' => $runId,
            'profile_key' => 'legacy_baseline',
            'reference_profile_key' => 'legacy_baseline',
            'is_active_profile' => false,
        ]);
        $this->assertDatabaseHas('ai_team_tier_tuning_candidates', [
            'ai_team_tier_tuning_run_id' => $runId,
            'profile_key' => 'active_profile',
            'reference_profile_key' => 'legacy_baseline',
            'is_active_profile' => true,
        ]);
        $this->assertDatabaseHas('ai_team_tier_tuning_candidates', [
            'ai_team_tier_tuning_run_id' => $runId,
            'profile_key' => 'incremental_gd00_fixed_v0',
            'reference_profile_key' => 'active_profile',
            'accepted' => false,
        ]);

        $logPath = storage_path('logs/administration/tier-squadre/tier-squadre.log');
        $this->assertStringContainsString('[tier_squadre][auto_tuning][INFO]', File::get($logPath));
    }

    public function test_promoted_team_transition_penalty_reduces_overrating(): void
    {
        $targetLeagueSeasonId = $this->seedTierScenario();
        $countryId = DB::table('countries')->where('name', 'Italy')->value('id');
        $serieBId = DB::table('leagues')->where('name', 'Serie B')->value('id');
        $season2025Id = DB::table('seasons')->where('season_key', 2025)->value('id');

        $serieB2025Id = DB::table('league_seasons')->insertGetId([
            'league_id' => $serieBId,
            'season_id' => $season2025Id,
            'is_current' => false,
            'status' => 'active',
            'start_date' => null,
            'end_date' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $teamId = DB::table('teams')->insertGetId([
            'country_id' => $countryId,
            'name' => 'Promoted FC',
            'short_name' => 'Promoted',
            'code' => 'PFC',
            'crest_url' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('league_season_teams')->insert([
            'league_season_id' => $targetLeagueSeasonId,
            'team_id' => $teamId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $leagueSeasonTeamId = DB::table('league_season_teams')->insertGetId([
            'league_season_id' => $serieB2025Id,
            'team_id' => $teamId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('league_season_team_standings')->insert([
            'league_season_team_id' => $leagueSeasonTeamId,
            'position' => 1,
            'played_games' => 38,
            'won' => null,
            'draw' => null,
            'lost' => null,
            'points' => 82,
            'goals_for' => 75,
            'goals_against' => 30,
            'goal_difference' => 45,
            'stage_name' => null,
            'group_name' => null,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $penalizedRow = collect((new TeamTieringService())->analyze($targetLeagueSeasonId)['rows'])
            ->firstWhere('team_name', 'Promoted FC');

        DB::table('team_tier_settings')
            ->where('setting_group', 'transition_penalties')
            ->where('setting_key', 'by_case')
            ->update(['value' => json_encode(['promoted_from_lower_league' => 1.0])]);

        $unpenalizedRow = collect((new TeamTieringService())->analyze($targetLeagueSeasonId)['rows'])
            ->firstWhere('team_name', 'Promoted FC');

        $this->assertSame(1.25, $penalizedRow['transition_penalty']);
        $this->assertSame(1.0, $unpenalizedRow['transition_penalty']);
        $this->assertGreaterThan($unpenalizedRow['score'], $penalizedRow['score']);
    }


    public function test_audit_reports_tier_readiness_without_writes(): void
    {
        $targetLeagueSeasonId = $this->seedTierScenario();

        $this->artisan('team-tiers:audit', [
            '--league-season-id' => $targetLeagueSeasonId,
            '--json' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('teams', [
            'name' => 'AC Milan',
            'tier_globale' => null,
        ]);

        $logPath = storage_path('logs/administration/tier-squadre/tier-squadre.log');
        $this->assertTrue(File::exists($logPath));
        $this->assertStringContainsString('Team tier readiness audit completed.', File::get($logPath));
    }

    public function test_legacy_team_update_command_delegates_to_v3_sync(): void
    {
        $targetLeagueSeasonId = $this->seedTierScenario();

        $this->artisan('teams:update-tiers', [
            '--league-season-id' => $targetLeagueSeasonId,
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('teams', [
            'name' => 'AC Milan',
            'tier_globale' => null,
        ]);
    }
    private function seedTierScenario(): int
    {
        $confederationId = DB::table('confederations')->insertGetId([
            'code' => 'UEFA',
            'name' => 'UEFA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $countryId = DB::table('countries')->insertGetId([
            'confederation_id' => $confederationId,
            'region' => 'Europe',
            'name' => 'Italy',
            'iso2' => 'IT',
            'iso3' => 'ITA',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $serieAId = DB::table('leagues')->insertGetId([
            'country_id' => $countryId,
            'name' => 'Serie A',
            'slug' => 'italy-serie-a',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $serieBId = DB::table('leagues')->insertGetId([
            'country_id' => $countryId,
            'name' => 'Serie B',
            'slug' => 'italy-serie-b',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $seasonIds = [];
        foreach ([2026 => '2026/27', 2025 => '2025/26', 2024 => '2024/25', 2023 => '2023/24', 2022 => '2022/23'] as $key => $label) {
            $seasonIds[$key] = DB::table('seasons')->insertGetId([
                'season_key' => $key,
                'label' => $label,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $targetLeagueSeasonId = DB::table('league_seasons')->insertGetId([
            'league_id' => $serieAId,
            'season_id' => $seasonIds[2026],
            'is_current' => true,
            'status' => 'active',
            'start_date' => '2026-08-23',
            'end_date' => '2027-05-30',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $historicalLeagueSeasonIds = [];
        foreach ([2025, 2024, 2023, 2022] as $key) {
            $historicalLeagueSeasonIds[$key] = DB::table('league_seasons')->insertGetId([
                'league_id' => $key === 2022 ? $serieBId : $serieAId,
                'season_id' => $seasonIds[$key],
                'is_current' => false,
                'status' => 'active',
                'start_date' => null,
                'end_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $milanId = DB::table('teams')->insertGetId([
            'country_id' => $countryId,
            'name' => 'AC Milan',
            'short_name' => 'Milan',
            'code' => 'MIL',
            'crest_url' => 'https://example.test/milan.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $romaId = DB::table('teams')->insertGetId([
            'country_id' => $countryId,
            'name' => 'AS Roma',
            'short_name' => 'Roma',
            'code' => 'ROM',
            'crest_url' => 'https://example.test/roma.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([$milanId, $romaId] as $teamId) {
            DB::table('league_season_teams')->insert([
                'league_season_id' => $targetLeagueSeasonId,
                'team_id' => $teamId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $standings = [
            $milanId => [2025 => [2, 80, 78, 35], 2024 => [3, 75, 72, 38], 2023 => [4, 70, 68, 42], 2022 => [1, 72, 66, 30]],
            $romaId => [2025 => [8, 60, 58, 48], 2024 => [6, 63, 60, 44], 2023 => [7, 61, 55, 46], 2022 => [4, 56, 49, 41]],
        ];

        foreach ($standings as $teamId => $teamStandings) {
            foreach ($teamStandings as $seasonKey => [$position, $points, $goalsFor, $goalsAgainst]) {
                $leagueSeasonTeamId = DB::table('league_season_teams')->insertGetId([
                    'league_season_id' => $historicalLeagueSeasonIds[$seasonKey],
                    'team_id' => $teamId,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('league_season_team_standings')->insert([
                    'league_season_team_id' => $leagueSeasonTeamId,
                    'position' => $position,
                    'played_games' => 38,
                    'won' => null,
                    'draw' => null,
                    'lost' => null,
                    'points' => $points,
                    'goals_for' => $goalsFor,
                    'goals_against' => $goalsAgainst,
                    'goal_difference' => $goalsFor - $goalsAgainst,
                    'stage_name' => null,
                    'group_name' => null,
                    'synced_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $targetLeagueSeasonId;
    }
}


