<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SyncLeagueSeasonTeamsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_sync_dry_run_reads_db_configured_provider_without_writes(): void
    {
        [$leagueSeasonId, $leagueId] = $this->seedLeagueSeason();
        $this->seedTeamProvider($leagueId);

        Http::fake([
            'api.example.test/*' => Http::response([
                'teams' => [
                    ['id' => 1, 'name' => 'Juventus', 'shortName' => 'Juve', 'tla' => 'JUV', 'crest' => 'https://example.test/juve.png'],
                    ['id' => 2, 'name' => 'Milan', 'shortName' => 'Milan', 'tla' => 'MIL', 'crest' => 'https://example.test/milan.png'],
                ],
            ]),
        ]);

        $this->artisan('teams:sync', [
            '--league-season-id' => $leagueSeasonId,
            '--json' => true,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('CHANGES_PENDING')
            ->expectsOutputToContain('Juventus')
            ->expectsOutputToContain('Milan');

        $this->assertDatabaseCount('teams', 0);
        $this->assertDatabaseCount('league_season_teams', 0);
        $this->assertDatabaseCount('team_provider_mappings', 0);
        $this->assertTrue(File::exists(storage_path('logs/administration/squadre/teams_sync.log')));
        $this->assertStringContainsString('[teams_sync][INFO]', File::get(storage_path('logs/administration/squadre/teams_sync.log')));
    }

    public function test_team_sync_apply_persists_teams_season_memberships_and_provider_mappings(): void
    {
        [$leagueSeasonId, $leagueId] = $this->seedLeagueSeason();
        $this->seedTeamProvider($leagueId);

        Http::fake([
            'api.example.test/*' => Http::response([
                'teams' => [
                    ['id' => 1, 'name' => 'Juventus', 'shortName' => 'Juve', 'tla' => 'JUV', 'crest' => 'https://example.test/juve.png'],
                    ['id' => 2, 'name' => 'Milan', 'shortName' => 'Milan', 'tla' => 'MIL', 'crest' => 'https://example.test/milan.png'],
                ],
            ]),
        ]);

        $this->artisan('teams:sync', [
            '--league-season-id' => $leagueSeasonId,
            '--apply' => true,
            '--json' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('teams', [
            'name' => 'Juventus',
            'short_name' => 'Juve',
            'code' => 'JUV',
        ]);
        $this->assertDatabaseHas('teams', [
            'name' => 'Milan',
            'short_name' => 'Milan',
            'code' => 'MIL',
        ]);
        $this->assertDatabaseCount('league_season_teams', 2);
        $this->assertDatabaseHas('team_provider_mappings', [
            'external_id' => '1',
            'external_name' => 'Juventus',
            'external_code' => 'JUV',
        ]);
    }

    /** @return array{0:int,1:int,2:int} */
    private function seedLeagueSeason(): array
    {
        $confederationId = DB::table('confederations')->insertGetId([
            'code' => 'UEFA',
            'name' => 'UEFA',
            'active' => true,
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

        $leagueId = DB::table('leagues')->insertGetId([
            'country_id' => $countryId,
            'name' => 'Serie A',
            'slug' => 'serie-a',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $seasonId = DB::table('seasons')->insertGetId([
            'season_key' => 2026,
            'label' => '2026/27',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $leagueSeasonId = DB::table('league_seasons')->insertGetId([
            'league_id' => $leagueId,
            'season_id' => $seasonId,
            'is_current' => true,
            'status' => 'active',
            'start_date' => null,
            'end_date' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$leagueSeasonId, $leagueId, $countryId];
    }

    private function seedTeamProvider(int $leagueId): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.example.test/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_runtime_configs')->insert([
            'data_provider_id' => $providerId,
            'is_enabled' => true,
            'priority' => 10,
            'role' => 'primary',
            'base_url' => 'https://api.example.test/v4',
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry_times' => 0,
            'retry_sleep_ms' => 0,
            'plan' => 'Free',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('league_provider_mappings')->insert([
            'league_id' => $leagueId,
            'data_provider_id' => $providerId,
            'external_id' => 'SA',
            'external_name' => 'Serie A',
            'external_country' => 'Italy',
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $endpointId = DB::table('data_provider_http_endpoints')->insertGetId([
            'data_provider_id' => $providerId,
            'capability' => 'teams',
            'operation' => 'by_season',
            'label' => 'Teams by season',
            'method' => 'GET',
            'endpoint' => 'competitions/{provider_competition_code}/teams',
            'query_params' => json_encode(['season' => '{season_year}']),
            'items_path' => 'teams',
            'is_enabled' => true,
            'validation_status' => 'test_passed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_payload_mappings')->insert([
            'data_provider_http_endpoint_id' => $endpointId,
            'field_mappings' => json_encode([
                'provider_team_id' => 'id',
                'team_name' => 'name',
                'short_name' => 'shortName',
                'team_code' => 'tla',
                'crest_url' => 'crest',
            ]),
            'required_fields' => json_encode(['provider_team_id', 'team_name']),
            'validation_status' => 'mapping_validated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
