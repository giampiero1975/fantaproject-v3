<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SyncLeagueSeasonStandingsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_standing_sync_dry_run_reads_db_configured_provider_without_writes(): void
    {
        [$leagueSeasonId, $leagueId] = $this->seedLeagueSeasonWithTeams();
        $this->seedStandingProvider($leagueId, $leagueSeasonId);
        $this->fakeStandings();

        $this->artisan('standings:sync', ['--league-season-id' => $leagueSeasonId, '--json' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('CHANGES_PENDING')
            ->expectsOutputToContain('Juventus')
            ->expectsOutputToContain('Milan');

        $this->assertDatabaseCount('league_season_team_standings', 0);
        $this->assertTrue(File::exists(storage_path('logs/administration/classifiche/standings_sync.log')));
        $this->assertStringContainsString('[standings_sync][INFO]', File::get(storage_path('logs/administration/classifiche/standings_sync.log')));
    }

    public function test_standing_sync_apply_persists_canonical_standings(): void
    {
        [$leagueSeasonId, $leagueId] = $this->seedLeagueSeasonWithTeams();
        $this->seedStandingProvider($leagueId, $leagueSeasonId);
        $this->fakeStandings();

        $this->artisan('standings:sync', ['--league-season-id' => $leagueSeasonId, '--apply' => true, '--json' => true])
            ->assertExitCode(0);

        $this->assertDatabaseCount('league_season_team_standings', 2);
        $this->assertDatabaseHas('league_season_team_standings', ['position' => 1, 'points' => 83, 'goal_difference' => 40]);
        $this->assertDatabaseHas('league_season_team_standings', ['position' => 2, 'points' => 76, 'goal_difference' => 30]);
    }

    /** @return array{0:int,1:int} */
    private function seedLeagueSeasonWithTeams(): array
    {
        $confederationId = DB::table('confederations')->insertGetId(['code' => 'UEFA', 'name' => 'UEFA', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $countryId = DB::table('countries')->insertGetId(['confederation_id' => $confederationId, 'region' => 'Europe', 'name' => 'Italy', 'iso2' => 'IT', 'iso3' => 'ITA', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $leagueId = DB::table('leagues')->insertGetId(['country_id' => $countryId, 'name' => 'Serie A', 'slug' => 'serie-a', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $seasonId = DB::table('seasons')->insertGetId(['season_key' => 2026, 'label' => '2026/27', 'created_at' => now(), 'updated_at' => now()]);
        $leagueSeasonId = DB::table('league_seasons')->insertGetId(['league_id' => $leagueId, 'season_id' => $seasonId, 'is_current' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        foreach ([['Juventus', 'JUV'], ['Milan', 'MIL']] as $team) {
            $teamId = DB::table('teams')->insertGetId(['country_id' => $countryId, 'name' => $team[0], 'code' => $team[1], 'created_at' => now(), 'updated_at' => now()]);
            DB::table('league_season_teams')->insert(['league_season_id' => $leagueSeasonId, 'team_id' => $teamId, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        return [$leagueSeasonId, $leagueId];
    }

    private function seedStandingProvider(int $leagueId, int $leagueSeasonId): void
    {
        $providerId = DB::table('data_providers')->insertGetId(['code' => 'football_data', 'name' => 'football-data.org', 'base_url' => 'https://api.example.test/v4', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('data_provider_runtime_configs')->insert(['data_provider_id' => $providerId, 'is_enabled' => true, 'priority' => 10, 'role' => 'primary', 'base_url' => 'https://api.example.test/v4', 'timeout' => 30, 'connect_timeout' => 10, 'retry_times' => 0, 'retry_sleep_ms' => 0, 'plan' => 'Free', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('league_provider_mappings')->insert(['league_id' => $leagueId, 'data_provider_id' => $providerId, 'external_id' => 'SA', 'external_name' => 'Serie A', 'external_country' => 'Italy', 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $memberships = DB::table('league_season_teams as lst')->join('teams as t', 't.id', '=', 'lst.team_id')->where('lst.league_season_id', $leagueSeasonId)->select('lst.id', 't.name', 't.code')->get();
        foreach ($memberships as $membership) {
            DB::table('team_provider_mappings')->insert(['league_season_team_id' => $membership->id, 'data_provider_id' => $providerId, 'external_id' => $membership->code === 'JUV' ? '1' : '2', 'external_name' => $membership->name, 'external_code' => $membership->code, 'created_at' => now(), 'updated_at' => now()]);
        }

        $endpointId = DB::table('data_provider_http_endpoints')->insertGetId(['data_provider_id' => $providerId, 'capability' => 'standings', 'operation' => 'by_season', 'label' => 'Standings by season', 'method' => 'GET', 'endpoint' => 'competitions/{provider_competition_code}/standings', 'query_params' => json_encode(['season' => '{season_year}']), 'items_path' => 'standings.0.table', 'is_enabled' => true, 'validation_status' => 'test_passed', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('data_provider_payload_mappings')->insert(['data_provider_http_endpoint_id' => $endpointId, 'field_mappings' => json_encode(['provider_team_id' => 'team.id', 'team_name' => 'team.name', 'team_code' => 'team.tla', 'position' => 'position', 'played_games' => 'playedGames', 'won' => 'won', 'draw' => 'draw', 'lost' => 'lost', 'points' => 'points', 'goals_for' => 'goalsFor', 'goals_against' => 'goalsAgainst', 'goal_difference' => 'goalDifference']), 'required_fields' => json_encode(['provider_team_id', 'team_name']), 'validation_status' => 'mapping_validated', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function fakeStandings(): void
    {
        Http::fake(['api.example.test/*' => Http::response(['standings' => [['table' => [
            ['position' => 1, 'team' => ['id' => 1, 'name' => 'Juventus', 'tla' => 'JUV'], 'playedGames' => 38, 'won' => 25, 'draw' => 8, 'lost' => 5, 'points' => 83, 'goalsFor' => 70, 'goalsAgainst' => 30, 'goalDifference' => 40],
            ['position' => 2, 'team' => ['id' => 2, 'name' => 'Milan', 'tla' => 'MIL'], 'playedGames' => 38, 'won' => 23, 'draw' => 7, 'lost' => 8, 'points' => 76, 'goalsFor' => 65, 'goalsAgainst' => 35, 'goalDifference' => 30],
        ]]]])]);
    }
}