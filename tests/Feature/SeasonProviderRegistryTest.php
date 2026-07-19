<?php

namespace Tests\Feature;

use App\Data\Providers\SeasonDataRequest;
use App\Services\Providers\SeasonProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SeasonProviderRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_loads_http_season_provider_from_database_configuration(): void
    {
        Http::fake([
            'api.football-data.org/*' => Http::response([
                'season' => [
                    'id' => 2494,
                    'startDate' => '2026-08-23',
                    'endDate' => '2027-05-30',
                    'currentMatchday' => 1,
                    'winner' => null,
                ],
                'standings' => [
                    [
                        'table' => [
                            ['team' => ['id' => 1, 'name' => 'Juventus']],
                            ['team' => ['id' => 2, 'name' => 'Milan']],
                        ],
                    ],
                ],
            ]),
        ]);

        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_runtime_configs')->insert([
            'data_provider_id' => $providerId,
            'is_enabled' => true,
            'priority' => 10,
            'role' => 'primary',
            'base_url' => 'https://api.football-data.org/v4',
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry_times' => 0,
            'retry_sleep_ms' => 0,
            'plan' => 'Free',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $endpointId = DB::table('data_provider_http_endpoints')->insertGetId([
            'data_provider_id' => $providerId,
            'capability' => 'seasons',
            'operation' => 'by_season',
            'label' => 'Stagione',
            'method' => 'GET',
            'endpoint' => 'competitions/{provider_competition_code}/standings',
            'query_params' => json_encode(['season' => '{season_year}']),
            'items_path' => null,
            'is_enabled' => true,
            'validation_status' => 'test_passed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_payload_mappings')->insert([
            'data_provider_http_endpoint_id' => $endpointId,
            'field_mappings' => json_encode([
                'season_id' => 'season.id',
                'start_date' => 'season.startDate',
                'end_date' => 'season.endDate',
                'list_teams' => 'map(standings.0.table, team_id=team.id, team_name=team.name)',
            ]),
            'required_fields' => json_encode(['season_id', 'start_date', 'end_date']),
            'validation_status' => 'mapping_validated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $providers = app(SeasonProviderRegistry::class)->all();

        $this->assertCount(1, $providers);

        $result = $providers[0]->fetchSeason(new SeasonDataRequest(2026, [
            'football_data' => 'SA',
        ]));

        $this->assertTrue($result->available);
        $this->assertSame('football_data', $result->provider);
        $this->assertSame('2494', $result->season?->externalId);
        $this->assertSame('2026-08-23', $result->season?->startDate);
        $this->assertSame('2027-05-30', $result->season?->endDate);
        $this->assertSame([
            ['team_id' => 1, 'team_name' => 'Juventus'],
            ['team_id' => 2, 'team_name' => 'Milan'],
        ], $result->season?->metadata['list_teams']);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.football-data.org/v4/competitions/SA/standings?season=2026');
    }

    public function test_season_sync_uses_http_season_provider_dates(): void
    {
        Http::fake([
            'api.football-data.org/*' => Http::response([
                'season' => [
                    'id' => 2494,
                    'startDate' => '2026-08-23',
                    'endDate' => '2027-05-30',
                ],
                'standings' => [],
            ]),
        ]);

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

        $providerId = $this->insertFootballDataSeasonProvider();

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

        $this->artisan('season:sync', [
            '--league-id' => $leagueId,
            '--provider-ref' => ['football_data=SA'],
            '--season' => 2026,
            '--history' => 0,
            '--apply' => true,
            '--json' => true,
        ])
            ->assertExitCode(0);

        $seasonId = DB::table('seasons')->where('season_key', 2026)->value('id');

        $this->assertDatabaseHas('league_seasons', [
            'league_id' => $leagueId,
            'season_id' => $seasonId,
            'start_date' => '2026-08-23',
            'end_date' => '2027-05-30',
        ]);

        $leagueSeasonId = DB::table('league_seasons')
            ->where('league_id', $leagueId)
            ->where('season_id', $seasonId)
            ->value('id');

        $this->assertDatabaseHas('league_season_provider_mappings', [
            'league_season_id' => $leagueSeasonId,
            'data_provider_id' => $providerId,
            'external_id' => 'SA',
            'external_year' => 2026,
        ]);

        $logPath = storage_path('logs/administration/season_management/season_sync.log');

        $this->assertTrue(File::exists($logPath));
        $this->assertStringContainsString('[season_sync][INFO] Season sync started.', File::get($logPath));
        $this->assertStringContainsString('[season_sync][INFO] Provider season result.', File::get($logPath));
    }

    public function test_season_provider_does_not_call_http_with_unresolved_endpoint_variables(): void
    {
        Http::fake();

        $providerId = $this->insertFootballDataSeasonProvider('competitions/{competition_code}/standings');

        $providers = app(SeasonProviderRegistry::class)->all();

        $result = $providers[0]->fetchSeason(new SeasonDataRequest(2026, [
            'football_data' => 'SA',
        ]));

        $this->assertFalse($result->available);
        $this->assertSame('unresolved_endpoint_template_variables', $result->reason);

        Http::assertNothingSent();
    }

    public function test_season_registry_ignores_inactive_runtime_providers(): void
    {
        $this->insertFootballDataSeasonProvider(runtimeEnabled: false);

        $this->assertSame([], app(SeasonProviderRegistry::class)->all());
    }

    public function test_season_provider_supports_season_label_template_variable(): void
    {
        Http::fake([
            'api.example.test/*' => Http::response([
                'season' => [
                    'id' => '2022-2023',
                    'startDate' => '2022-08-13',
                    'endDate' => '2023-06-04',
                ],
            ]),
        ]);

        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'season_label_provider',
            'name' => 'Season Label Provider',
            'base_url' => 'https://api.example.test',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_runtime_configs')->insert([
            'data_provider_id' => $providerId,
            'is_enabled' => true,
            'priority' => 30,
            'role' => 'fallback',
            'base_url' => 'https://api.example.test',
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry_times' => 0,
            'retry_sleep_ms' => 0,
            'plan' => 'Free',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $endpointId = DB::table('data_provider_http_endpoints')->insertGetId([
            'data_provider_id' => $providerId,
            'capability' => 'seasons',
            'operation' => 'by_season',
            'label' => 'Dettaglio stagione',
            'method' => 'GET',
            'endpoint' => 'season',
            'query_params' => json_encode([
                'league' => '{provider_competition_code}',
                's' => '{season_label}',
            ]),
            'items_path' => null,
            'is_enabled' => true,
            'validation_status' => 'test_passed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_payload_mappings')->insert([
            'data_provider_http_endpoint_id' => $endpointId,
            'field_mappings' => json_encode([
                'season_id' => 'season.id',
                'start_date' => 'season.startDate',
                'end_date' => 'season.endDate',
            ]),
            'required_fields' => json_encode(['season_id', 'start_date', 'end_date']),
            'validation_status' => 'mapping_validated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $provider = app(SeasonProviderRegistry::class)->all()[0];
        $result = $provider->fetchSeason(new SeasonDataRequest(2022, ['season_label_provider' => 'SA']));

        $this->assertTrue($result->available);
        $this->assertSame('2022-2023', $result->season?->externalId);
        $this->assertSame('2022-08-13', $result->season?->startDate);
        $this->assertSame('2023-06-04', $result->season?->endDate);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.example.test/season?league=SA&s=2022-2023');
    }

    public function test_season_registry_ignores_incomplete_payload_mappings(): void
    {
        $providerId = $this->insertFootballDataSeasonProvider();
        $endpointId = DB::table('data_provider_http_endpoints')
            ->where('data_provider_id', $providerId)
            ->where('capability', 'seasons')
            ->value('id');

        DB::table('data_provider_payload_mappings')
            ->where('data_provider_http_endpoint_id', $endpointId)
            ->update(['validation_status' => 'mapping_incomplete']);

        $this->assertSame([], app(SeasonProviderRegistry::class)->all());
    }

    private function insertFootballDataSeasonProvider(
        string $endpoint = 'competitions/{provider_competition_code}/standings',
        bool $runtimeEnabled = true,
    ): int
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_runtime_configs')->insert([
            'data_provider_id' => $providerId,
            'is_enabled' => $runtimeEnabled,
            'priority' => 10,
            'role' => 'primary',
            'base_url' => 'https://api.football-data.org/v4',
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry_times' => 0,
            'retry_sleep_ms' => 0,
            'plan' => 'Free',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $endpointId = DB::table('data_provider_http_endpoints')->insertGetId([
            'data_provider_id' => $providerId,
            'capability' => 'seasons',
            'operation' => 'by_season',
            'label' => 'Stagione',
            'method' => 'GET',
            'endpoint' => $endpoint,
            'query_params' => json_encode(['season' => '{season_year}']),
            'items_path' => null,
            'is_enabled' => true,
            'validation_status' => 'test_passed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_payload_mappings')->insert([
            'data_provider_http_endpoint_id' => $endpointId,
            'field_mappings' => json_encode([
                'season_id' => 'season.id',
                'start_date' => 'season.startDate',
                'end_date' => 'season.endDate',
            ]),
            'required_fields' => json_encode(['season_id', 'start_date', 'end_date']),
            'validation_status' => 'mapping_validated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $providerId;
    }
}
