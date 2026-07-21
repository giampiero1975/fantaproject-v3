<?php

namespace Tests\Feature;

use App\Data\Providers\TeamDataRequest;
use App\Services\Providers\TeamProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class TeamProviderRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_loads_http_team_provider_from_database_configuration(): void
    {
        Http::fake([
            'www.thesportsdb.com/*' => Http::response([
                'teams' => [
                    [
                        'idTeam' => '133602',
                        'strTeam' => 'Juventus',
                        'strTeamShort' => 'JUV',
                        'strCountry' => 'Italy',
                        'strBadge' => 'https://example.test/juventus.png',
                    ],
                    [
                        'idTeam' => '133613',
                        'strTeam' => 'AC Milan',
                        'strTeamShort' => 'MIL',
                        'strCountry' => 'Italy',
                        'strBadge' => 'https://example.test/milan.png',
                    ],
                ],
            ]),
        ]);

        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'thesportsdb',
            'name' => 'TheSportsDB',
            'base_url' => 'https://www.thesportsdb.com/api/v1/json/3',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_runtime_configs')->insert([
            'data_provider_id' => $providerId,
            'is_enabled' => true,
            'priority' => 30,
            'role' => 'fallback',
            'base_url' => 'https://www.thesportsdb.com/api/v1/json/3',
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry_times' => 0,
            'retry_sleep_ms' => 0,
            'plan' => 'Free',
            'metadata' => json_encode(['onboarding_state' => 'configure_runtime']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $endpointId = DB::table('data_provider_http_endpoints')->insertGetId([
            'data_provider_id' => $providerId,
            'capability' => 'teams',
            'operation' => 'by_competition',
            'label' => 'Teams by league',
            'method' => 'GET',
            'endpoint' => 'lookup_all_teams.php',
            'query_params' => json_encode(['id' => '{provider_competition_code}']),
            'items_path' => 'teams',
            'is_enabled' => true,
            'validation_status' => 'test_passed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_payload_mappings')->insert([
            'data_provider_http_endpoint_id' => $endpointId,
            'field_mappings' => json_encode([
                'provider_team_id' => 'idTeam',
                'team_name' => 'strTeam',
                'team_code' => 'strTeamShort',
                'country_name' => 'strCountry',
                'crest_url' => 'strBadge',
            ]),
            'required_fields' => json_encode(['provider_team_id', 'team_name']),
            'validation_status' => 'mapping_validated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $providers = app(TeamProviderRegistry::class)->all();

        $this->assertCount(1, $providers);
        $this->assertSame('thesportsdb', $providers[0]->key());

        $result = $providers[0]->fetchTeams(new TeamDataRequest(2026, [
            'thesportsdb' => '4332',
        ]));

        $this->assertTrue($result->available);
        $this->assertCount(2, $result->teams);
        $this->assertSame('thesportsdb', $result->teams[0]->provider);
        $this->assertSame('133602', $result->teams[0]->externalId);
        $this->assertSame('Juventus', $result->teams[0]->name);
        $this->assertSame('JUV', $result->teams[0]->code);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://www.thesportsdb.com/api/v1/json/3/lookup_all_teams.php?id=4332');
    }

    public function test_registry_ignores_provider_without_http_team_configuration(): void
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
            'is_enabled' => true,
            'priority' => 10,
            'role' => 'primary',
            'base_url' => 'https://api.football-data.org/v4',
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry_times' => 0,
            'retry_sleep_ms' => 0,
            'plan' => 'Free',
            'metadata' => json_encode(['onboarding_state' => 'configure_runtime']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame([], app(TeamProviderRegistry::class)->all());
    }
}
