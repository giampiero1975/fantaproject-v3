<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProviderManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::findOrCreate('admin', 'web');
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    public function test_provider_registration_starts_disabled_until_runtime_calls_are_configured(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.providers.store'), [
            'code' => 'test_provider',
            'name' => 'Test Provider',
            'base_url' => 'https://api.test-provider.example',
            'role' => 'fallback',
            'priority' => 30,
            'plan' => 'Basic',
            'credential_required' => 1,
            'credential_key' => 'api_token',
            'credential_value' => 'secret-token',
            'capabilities' => ['competitions', 'seasons'],
        ]);

        $response->assertSessionHasNoErrors();

        $provider = DB::table('data_providers')->where('code', 'test_provider')->first();
        $this->assertNotNull($provider);
        $this->assertFalse((bool) $provider->active);

        $this->assertDatabaseHas('data_provider_runtime_configs', [
            'data_provider_id' => $provider->id,
            'is_enabled' => 0,
            'priority' => 30,
            'role' => 'fallback',
            'plan' => 'Basic',
        ]);

        $credential = DB::table('data_provider_credentials')->where('data_provider_id', $provider->id)->first();
        $this->assertNotNull($credential);
        $this->assertSame('api_token', $credential->credential_key);
        $this->assertSame('secret-token', Crypt::decryptString($credential->encrypted_value));
    }

    public function test_provider_without_runtime_configuration_can_be_registered_from_ui_without_credential(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.providers.store'), [
            'code' => 'thesportsdb',
            'name' => 'TheSportsDB',
            'base_url' => 'https://www.thesportsdb.com',
            'role' => 'fallback',
            'priority' => 30,
            'plan' => 'Free',
            'credential_required' => 0,
            'capabilities' => ['competitions', 'seasons', 'teams'],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');

        $provider = DB::table('data_providers')->where('code', 'thesportsdb')->first();
        $this->assertNotNull($provider);
        $this->assertFalse((bool) $provider->active);

        $runtime = DB::table('data_provider_runtime_configs')->where('data_provider_id', $provider->id)->first();
        $this->assertNotNull($runtime);
        $this->assertFalse((bool) $runtime->is_enabled);

        $metadata = json_decode($runtime->metadata, true);
        $this->assertSame('configure_runtime', $metadata['onboarding_state']);
        $this->assertFalse($metadata['credential_required']);
        $this->assertSame(['competitions', 'seasons', 'teams'], $metadata['capabilities']);

        $this->assertDatabaseMissing('data_provider_credentials', [
            'data_provider_id' => $provider->id,
        ]);
    }

    public function test_provider_code_is_normalized_before_validation(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.providers.store'), [
            'code' => 'The Sports DB',
            'name' => 'TheSportsDB',
            'base_url' => 'https://www.thesportsdb.com/api/v1/json/3',
            'role' => 'fallback',
            'priority' => 30,
            'plan' => 'Free',
            'credential_required' => 0,
            'capabilities' => ['competitions', 'seasons', 'teams'],
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('data_providers', [
            'code' => 'the_sports_db',
            'name' => 'TheSportsDB',
            'active' => 0,
        ]);
    }

    public function test_provider_management_marks_registered_provider_without_runtime_configuration(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'thesportsdb',
            'name' => 'TheSportsDB',
            'base_url' => 'https://www.thesportsdb.com/api/v1/json/3',
            'active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_runtime_configs')->insert([
            'data_provider_id' => $providerId,
            'is_enabled' => false,
            'priority' => 30,
            'role' => 'fallback',
            'base_url' => 'https://www.thesportsdb.com/api/v1/json/3',
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry_times' => 3,
            'retry_sleep_ms' => 500,
            'plan' => 'Free',
            'metadata' => json_encode([
                'onboarding_state' => 'configure_runtime',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.providers.index'))
            ->assertOk()
            ->assertSee('TheSportsDB')
            ->assertSee('Da configurare')
            ->assertSee('Configura runtime')
            ->assertDontSee('nativo')
            ->assertDontSee('Installa adapter per attivare');
    }

    public function test_provider_management_marks_http_configured_provider(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'thesportsdb',
            'name' => 'TheSportsDB',
            'base_url' => 'https://www.thesportsdb.com/api/v1/json/3',
            'active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_runtime_configs')->insert([
            'data_provider_id' => $providerId,
            'is_enabled' => false,
            'priority' => 30,
            'role' => 'fallback',
            'base_url' => 'https://www.thesportsdb.com/api/v1/json/3',
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry_times' => 3,
            'retry_sleep_ms' => 500,
            'plan' => 'Free',
            'metadata' => json_encode(['onboarding_state' => 'configure_runtime']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_http_endpoints')->insert([
            'data_provider_id' => $providerId,
            'capability' => 'competitions',
            'operation' => 'list',
            'label' => 'Leagues',
            'method' => 'GET',
            'endpoint' => 'all_leagues.php',
            'items_path' => null,
            'is_enabled' => true,
            'validation_status' => 'saved_not_tested',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.providers.index'))
            ->assertOk()
            ->assertSee('Configurato')
            ->assertSee('HTTP capability:')
            ->assertSee('Stato corrente: CONFIGURATO')
            ->assertSee('Hai configurato via UI:')
            ->assertSee('Runtime squadre:')
            ->assertSee('non pronto, manca endpoint teams')
            ->assertSee('Cosa manca per usarlo?')
            ->assertDontSee('nativo')
            ->assertDontSee('Adapter richiesto')
            ->assertDontSee('Installa adapter per attivare');
    }

    public function test_provider_management_does_not_offer_code_defined_adapters_in_registration_ui(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.providers.index'))
            ->assertOk()
            ->assertDontSee('Adapter installato disponibile')
            ->assertDontSee('data-installed-adapter', false)
            ->assertDontSee('data-code="api_football"', false)
            ->assertDontSee('data-credential-key="api_key"', false);
    }

    public function test_http_adapter_configuration_page_can_be_opened_from_provider_management(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'thesportsdb',
            'name' => 'TheSportsDB',
            'base_url' => 'https://www.thesportsdb.com/api/v1/json/3',
            'active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_runtime_configs')->insert([
            'data_provider_id' => $providerId,
            'is_enabled' => false,
            'priority' => 30,
            'role' => 'fallback',
            'base_url' => 'https://www.thesportsdb.com/api/v1/json/3',
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry_times' => 3,
            'retry_sleep_ms' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.providers.http-adapter.configure', $providerId))
            ->assertOk()
            ->assertSee('HTTP Adapter')
            ->assertSee('Test request')
            ->assertSee('Items path')
            ->assertSee('Field mapping')
            ->assertSee('Aggiungi nuovo campo')
            ->assertSee('Crea campo interno');
    }

    public function test_http_adapter_configuration_does_not_prefill_mapping_placeholders_without_saved_endpoint(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.providers.http-adapter.configure', $providerId))
            ->assertOk()
            ->assertSee('name="items_path" x-ref="itemsPath" value=""', false)
            ->assertSee('<textarea name="field_mappings"', false)
            ->assertDontSee('provider_competition_code=code')
            ->assertDontSee('country_name=area.name');
    }

    public function test_http_adapter_contract_fields_are_loaded_from_database(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_contract_fields')
            ->where('capability', 'competitions')
            ->where('field_key', 'provider_competition_code')
            ->update([
                'label' => 'Chiave competizione provider',
                'description' => 'Descrizione modificata da tabella contratto.',
                'updated_at' => now(),
            ]);

        $this->actingAs($this->admin)
            ->get(route('admin.providers.http-adapter.configure', $providerId))
            ->assertOk()
            ->assertSee('Chiave competizione provider')
            ->assertSee('Descrizione modificata da tabella contratto.');
    }

    public function test_provider_management_writes_functional_logs(): void
    {
        File::deleteDirectory(storage_path('logs/administration/provider_managment'));
        File::ensureDirectoryExists(storage_path('logs/administration/provider_managment'));

        $logPath = storage_path('logs/administration/provider_managment/provider_management.log');
        File::put($logPath, 'old log line that must be replaced');

        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.providers.http-adapter.configure', $providerId))
            ->assertOk();

        $this->assertFileExists($logPath);
        $this->assertStringContainsString('[http_adapter_configuration][info]', File::get($logPath));
        $this->assertStringContainsString('HTTP adapter configuration page requested.', File::get($logPath));
        $this->assertStringContainsString('Provider Management', File::get($logPath));
        $this->assertStringNotContainsString('old log line that must be replaced', File::get($logPath));
    }

    public function test_http_adapter_test_request_builds_preview_from_mapping(): void
    {
        Http::fake([
            'www.thesportsdb.com/*' => Http::response([
                'leagues' => [
                    [
                        'idLeague' => '4332',
                        'strLeague' => 'Italian Serie A',
                        'strCountry' => 'Italy',
                    ],
                ],
            ]),
        ]);

        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'thesportsdb',
            'name' => 'TheSportsDB',
            'base_url' => 'https://www.thesportsdb.com/api/v1/json/3',
            'active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.providers.http-adapter.test', $providerId), [
                'capability' => 'competitions',
                'operation' => 'list',
                'method' => 'GET',
                'endpoint' => 'search_all_leagues.php',
                'query_params' => 'c=Italy',
                'items_path' => 'leagues',
                'field_mappings' => "provider_competition_code=idLeague\ncompetition_name=strLeague\ncountry_name=strCountry",
            ]);

        $response->assertRedirect();

        $result = session('http_adapter_test_result');

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['status']);
        $this->assertSame(1, $result['items_count']);
        $this->assertSame([
            'provider_competition_code' => '4332',
            'competition_name' => 'Italian Serie A',
            'country_name' => 'Italy',
        ], $result['normalized_preview']);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'search_all_leagues.php'));
    }

    public function test_http_adapter_test_request_can_pluck_nested_arrays_into_json_field(): void
    {
        Http::fake([
            'api.football-data.org/*' => Http::response([
                'season' => [
                    'id' => 2494,
                    'startDate' => '2026-08-23',
                    'endDate' => '2027-05-30',
                ],
                'standings' => [
                    [
                        'table' => [
                            ['team' => ['id' => 109, 'name' => 'Juventus FC']],
                            ['team' => ['id' => 98, 'name' => 'AC Milan']],
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

        foreach ([
            ['season_id', 'ID stagione', 'integer', true, 10],
            ['start_date', 'Inizio stagione', 'date', true, 20],
            ['end_date', 'Fine stagione', 'date', true, 30],
            ['list_teams', 'Lista squadre', 'json', false, 40],
        ] as [$fieldKey, $label, $type, $required, $sortOrder]) {
            DB::table('data_provider_contract_fields')->insert([
                'capability' => 'seasons',
                'operation' => 'by_season',
                'field_key' => $fieldKey,
                'label' => $label,
                'description' => $label,
                'data_type' => $type,
                'is_required' => $required,
                'sort_order' => $sortOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($this->admin)
            ->post(route('admin.providers.http-adapter.test', $providerId), [
                'capability' => 'seasons',
                'operation' => 'by_season',
                'method' => 'GET',
                'endpoint' => 'competitions/{provider_competition_code}/standings',
                'query_params' => 'season={season_year}',
                'test_variables' => "provider_competition_code=SA\nseason_year=2026",
                'items_path' => '',
                'field_mappings' => "season_id=season.id\nstart_date=season.startDate\nend_date=season.endDate\nlist_teams=pluck(standings.0.table, team.id)",
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $result = session('http_adapter_test_result');

        $this->assertSame([
            'season_id' => 2494,
            'start_date' => '2026-08-23',
            'end_date' => '2027-05-30',
            'list_teams' => [109, 98],
        ], $result['normalized_preview']);
    }

    public function test_http_adapter_test_request_can_map_nested_arrays_into_json_objects(): void
    {
        Http::fake([
            'api.football-data.org/*' => Http::response([
                'season' => [
                    'id' => 2494,
                    'startDate' => '2026-08-23',
                    'endDate' => '2027-05-30',
                ],
                'standings' => [
                    [
                        'table' => [
                            [
                                'position' => 1,
                                'team' => ['id' => 109, 'name' => 'Juventus FC'],
                            ],
                            [
                                'position' => 2,
                                'team' => ['id' => 98, 'name' => 'AC Milan'],
                            ],
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

        foreach ([
            ['season_id', 'ID stagione', 'integer', true, 10],
            ['list_teams', 'Lista squadre', 'json', false, 20],
        ] as [$fieldKey, $label, $type, $required, $sortOrder]) {
            DB::table('data_provider_contract_fields')->insert([
                'capability' => 'seasons',
                'operation' => 'by_season',
                'field_key' => $fieldKey,
                'label' => $label,
                'description' => $label,
                'data_type' => $type,
                'is_required' => $required,
                'sort_order' => $sortOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($this->admin)
            ->post(route('admin.providers.http-adapter.test', $providerId), [
                'capability' => 'seasons',
                'operation' => 'by_season',
                'method' => 'GET',
                'endpoint' => 'competitions/{provider_competition_code}/standings',
                'query_params' => 'season={season_year}',
                'test_variables' => "provider_competition_code=SA\nseason_year=2026",
                'items_path' => '',
                'field_mappings' => "season_id=season.id\nlist_teams=map(standings.0.table, provider_team_id=team.id, team_name=team.name, position=position)",
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $result = session('http_adapter_test_result');

        $this->assertSame([
            'season_id' => 2494,
            'list_teams' => [
                [
                    'provider_team_id' => 109,
                    'team_name' => 'Juventus FC',
                    'position' => 1,
                ],
                [
                    'provider_team_id' => 98,
                    'team_name' => 'AC Milan',
                    'position' => 2,
                ],
            ],
        ], $result['normalized_preview']);
    }

    public function test_http_adapter_test_request_resolves_template_variables_without_persisting_them(): void
    {
        Http::fake([
            'api.football-data.org/*' => Http::response([
                'standings' => [
                    [
                        'table' => [
                            [
                                'position' => 1,
                                'team' => ['id' => 109, 'name' => 'Juventus FC'],
                                'points' => 80,
                            ],
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

        $this->actingAs($this->admin)
            ->post(route('admin.providers.http-adapter.test', $providerId), [
                'capability' => 'competitions',
                'operation' => 'by_competition',
                'method' => 'GET',
                'endpoint' => 'competitions/{provider_competition_code}/standings',
                'query_params' => 'season={season_year}',
                'test_variables' => "provider_competition_code=SA\nseason_year=2024",
                'items_path' => 'standings.0.table',
                'field_mappings' => '',
            ])
            ->assertRedirect();

        $result = session('http_adapter_test_result');

        $this->assertTrue($result['ok']);
        $this->assertSame('https://api.football-data.org/v4/competitions/SA/standings', $result['resolved_url']);
        $this->assertSame(['season' => '2024'], $result['resolved_query']);
        $this->assertSame(1, $result['items_count']);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'competitions/SA/standings')
            && str_contains($request->url(), 'season=2024'));
    }

    public function test_http_adapter_test_warns_when_successful_response_has_no_mappable_payload(): void
    {
        File::deleteDirectory(storage_path('logs/administration/provider_managment'));

        Http::fake([
            'api.football-data.org/*' => Http::response('', 200, ['Content-Type' => 'text/html']),
        ]);

        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->copyCompetitionContractFieldsToOperation('detail');

        $this->actingAs($this->admin)
            ->post(route('admin.providers.http-adapter.test', $providerId), [
                'capability' => 'competitions',
                'operation' => 'detail',
                'method' => 'GET',
                'endpoint' => 'competitions/SA',
                'query_params' => '',
                'items_path' => '',
                'field_mappings' => "provider_competition_code=code\ncompetition_name=name\ncountry_name=area.name",
            ])
            ->assertRedirect();

        $result = session('http_adapter_test_result');

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['status']);
        $this->assertSame(0, $result['items_count']);
        $this->assertSame('Risposta HTTP 200, ma il corpo non e JSON valido o risulta vuoto.', $result['warning']);

        $log = File::get(storage_path('logs/administration/provider_managment/provider_management.log'));
        $this->assertStringContainsString('[http_adapter_test][warning]', $log);
        $this->assertStringContainsString('HTTP adapter test returned no mappable payload.', $log);
    }

    public function test_http_adapter_test_log_survives_redirect_back_to_configuration_page(): void
    {
        File::deleteDirectory(storage_path('logs/administration/provider_managment'));

        Http::fake([
            'www.thesportsdb.com/*' => Http::response([
                'leagues' => [
                    [
                        'idLeague' => '4332',
                        'strLeague' => 'Italian Serie A',
                        'strCountry' => 'Italy',
                    ],
                ],
            ]),
        ]);

        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'thesportsdb',
            'name' => 'TheSportsDB',
            'base_url' => 'https://www.thesportsdb.com/api/v1/json/3',
            'active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.providers.http-adapter.test', $providerId), [
                'capability' => 'competitions',
                'operation' => 'list',
                'method' => 'GET',
                'endpoint' => 'search_all_leagues.php',
                'query_params' => 'c=Italy',
                'items_path' => 'leagues',
                'field_mappings' => "provider_competition_code=idLeague\ncompetition_name=strLeague\ncountry_name=strCountry",
            ])
            ->assertRedirect();

        $this->get(route('admin.providers.http-adapter.configure', $providerId))
            ->assertOk();

        $log = File::get(storage_path('logs/administration/provider_managment/provider_management.log'));
        $this->assertStringContainsString('[http_adapter_test][info] HTTP adapter test requested.', $log);
        $this->assertStringContainsString('[http_adapter_configuration][info] HTTP adapter configuration page requested.', $log);
    }

    public function test_http_adapter_test_request_uses_database_configured_header_credentials(): void
    {
        Http::fake([
            'api.football-data.org/*' => Http::response([
                'competitions' => [
                    [
                        'id' => 2019,
                        'name' => 'Serie A',
                        'code' => 'SA',
                        'area' => ['name' => 'Italy', 'code' => 'ITA'],
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

        DB::table('data_provider_credentials')->insert([
            'data_provider_id' => $providerId,
            'environment' => app()->environment(),
            'credential_key' => 'token',
            'encrypted_value' => Crypt::encryptString('secret-football-data-token'),
            'is_active' => true,
            'rotated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_configurations')->insert([
            [
                'data_provider_id' => $providerId,
                'key' => 'auth_type',
                'value' => 'header',
                'value_type' => 'string',
                'environment' => null,
                'is_secret' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'data_provider_id' => $providerId,
                'key' => 'credential_key',
                'value' => 'token',
                'value_type' => 'string',
                'environment' => null,
                'is_secret' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'data_provider_id' => $providerId,
                'key' => 'auth_header_name',
                'value' => 'X-Auth-Token',
                'value_type' => 'string',
                'environment' => null,
                'is_secret' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.providers.http-adapter.test', $providerId), [
                'capability' => 'competitions',
                'operation' => 'list',
                'method' => 'GET',
                'endpoint' => 'competitions',
                'query_params' => '',
                'items_path' => 'competitions',
                'field_mappings' => "provider_competition_code=code\ncompetition_name=name\ncountry_name=area.name",
            ])
            ->assertRedirect();

        Http::assertSent(fn ($request): bool => $request->hasHeader('X-Auth-Token', 'secret-football-data-token'));
    }

    public function test_http_adapter_test_request_uses_alternate_database_configured_header_credentials(): void
    {
        Http::fake([
            'v3.football.api-sports.io/*' => Http::response([
                'response' => [
                    [
                        'league' => ['id' => 135, 'name' => 'Serie A'],
                        'country' => ['name' => 'Italy', 'code' => 'IT'],
                    ],
                ],
            ]),
        ]);

        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'api_football',
            'name' => 'API-Football',
            'base_url' => 'https://v3.football.api-sports.io',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_credentials')->insert([
            'data_provider_id' => $providerId,
            'environment' => app()->environment(),
            'credential_key' => 'api_key',
            'encrypted_value' => Crypt::encryptString('secret-api-football-key'),
            'is_active' => true,
            'rotated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_configurations')->insert([
            [
                'data_provider_id' => $providerId,
                'key' => 'auth_type',
                'value' => 'header',
                'value_type' => 'string',
                'environment' => null,
                'is_secret' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'data_provider_id' => $providerId,
                'key' => 'credential_key',
                'value' => 'api_key',
                'value_type' => 'string',
                'environment' => null,
                'is_secret' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'data_provider_id' => $providerId,
                'key' => 'auth_header_name',
                'value' => 'x-apisports-key',
                'value_type' => 'string',
                'environment' => null,
                'is_secret' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.providers.http-adapter.test', $providerId), [
                'capability' => 'competitions',
                'operation' => 'list',
                'method' => 'GET',
                'endpoint' => 'leagues',
                'query_params' => 'id=135',
                'items_path' => 'response',
                'field_mappings' => "provider_competition_code=league.id\ncompetition_name=league.name\ncountry_name=country.name",
            ])
            ->assertRedirect();

        Http::assertSent(fn ($request): bool => $request->hasHeader('x-apisports-key', 'secret-api-football-key'));
    }

    public function test_http_adapter_mapping_can_be_saved_to_runtime_tables(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'thesportsdb',
            'name' => 'TheSportsDB',
            'base_url' => 'https://www.thesportsdb.com/api/v1/json/3',
            'active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.providers.http-adapter.save', $providerId), [
                'capability' => 'competitions',
                'operation' => 'list',
                'label' => 'Lista leghe TheSportsDB',
                'method' => 'GET',
                'endpoint' => 'search_all_leagues.php',
                'query_params' => 'c=Italy',
                'items_path' => 'countries',
                'field_mappings' => "provider_competition_code=idLeague\ncompetition_name=strLeague\ncountry_name=strCountry",
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $endpoint = DB::table('data_provider_http_endpoints')
            ->where('data_provider_id', $providerId)
            ->where('capability', 'competitions')
            ->where('operation', 'list')
            ->first();

        $this->assertNotNull($endpoint);
        $this->assertSame('Lista leghe TheSportsDB', $endpoint->label);
        $this->assertSame('GET', $endpoint->method);
        $this->assertSame('search_all_leagues.php', $endpoint->endpoint);
        $this->assertSame('countries', $endpoint->items_path);
        $this->assertTrue((bool) $endpoint->is_enabled);
        $this->assertSame('saved_not_tested', $endpoint->validation_status);
        $this->assertSame(['c' => 'Italy'], json_decode($endpoint->query_params, true));

        $mapping = DB::table('data_provider_payload_mappings')
            ->where('data_provider_http_endpoint_id', $endpoint->id)
            ->first();

        $this->assertNotNull($mapping);
        $this->assertSame('mapping_validated', $mapping->validation_status);
        $this->assertSame([
            'provider_competition_code' => 'idLeague',
            'competition_name' => 'strLeague',
            'country_name' => 'strCountry',
        ], json_decode($mapping->field_mappings, true));
    }

    public function test_http_adapter_mapping_saves_template_variables_as_placeholders(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.providers.http-adapter.save', $providerId), [
                'capability' => 'competitions',
                'operation' => 'by_competition',
                'method' => 'GET',
                'endpoint' => 'competitions/{provider_competition_code}/standings',
                'query_params' => 'season={season_year}',
                'test_variables' => "provider_competition_code=SA\nseason_year=2024",
                'items_path' => 'standings.0.table',
                'field_mappings' => '',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $endpoint = DB::table('data_provider_http_endpoints')
            ->where('data_provider_id', $providerId)
            ->where('capability', 'competitions')
            ->where('operation', 'by_competition')
            ->first();

        $this->assertNotNull($endpoint);
        $this->assertSame('competitions/{provider_competition_code}/standings', $endpoint->endpoint);
        $this->assertSame(['season' => '{season_year}'], json_decode($endpoint->query_params, true));
    }

    public function test_incomplete_http_mapping_keeps_endpoint_enabled_for_lab_usage(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'thesportsdb',
            'name' => 'TheSportsDB',
            'base_url' => 'https://www.thesportsdb.com/api/v1/json/3',
            'active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.providers.http-adapter.save', $providerId), [
                'capability' => 'competitions',
                'operation' => 'list',
                'label' => 'All leagues raw list',
                'method' => 'GET',
                'endpoint' => 'all_leagues.php',
                'items_path' => '',
                'field_mappings' => "provider_competition_code=idLeague\ncompetition_name=strLeague",
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $endpoint = DB::table('data_provider_http_endpoints')
            ->where('data_provider_id', $providerId)
            ->where('capability', 'competitions')
            ->where('operation', 'list')
            ->first();

        $this->assertNotNull($endpoint);
        $this->assertTrue((bool) $endpoint->is_enabled);

        $mapping = DB::table('data_provider_payload_mappings')
            ->where('data_provider_http_endpoint_id', $endpoint->id)
            ->first();

        $this->assertNotNull($mapping);
        $this->assertSame('mapping_incomplete', $mapping->validation_status);
    }

    public function test_http_adapter_mapping_blocks_accidental_overwrite_when_configuration_was_not_loaded(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $endpointId = DB::table('data_provider_http_endpoints')->insertGetId([
            'data_provider_id' => $providerId,
            'capability' => 'competitions',
            'operation' => 'list',
            'label' => 'Competizioni NAZIONI',
            'method' => 'GET',
            'endpoint' => 'competitions',
            'query_params' => null,
            'body_template' => null,
            'items_path' => 'competitions',
            'is_enabled' => true,
            'validation_status' => 'test_passed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_payload_mappings')->insert([
            'data_provider_http_endpoint_id' => $endpointId,
            'field_mappings' => json_encode([
                'provider_competition_code' => 'code',
                'competition_name' => 'name',
                'country_name' => 'area.name',
            ]),
            'required_fields' => json_encode(['provider_competition_code', 'competition_name', 'country_name']),
            'validation_status' => 'mapping_validated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.providers.http-adapter.save', $providerId), [
                'capability' => 'competitions',
                'operation' => 'list',
                'label' => 'Errore accidentale',
                'method' => 'GET',
                'endpoint' => 'wrong-endpoint',
                'query_params' => '',
                'items_path' => '',
                'field_mappings' => "provider_competition_code=code\ncompetition_name=name\ncountry_name=area.name",
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('configuration');

        $endpoint = DB::table('data_provider_http_endpoints')->where('id', $endpointId)->first();

        $this->assertSame('Competizioni NAZIONI', $endpoint->label);
        $this->assertSame('competitions', $endpoint->endpoint);
        $this->assertSame('competitions', $endpoint->items_path);
    }

    public function test_http_adapter_mapping_allows_updating_loaded_configuration(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $endpointId = DB::table('data_provider_http_endpoints')->insertGetId([
            'data_provider_id' => $providerId,
            'capability' => 'competitions',
            'operation' => 'list',
            'label' => 'Competizioni',
            'method' => 'GET',
            'endpoint' => 'competitions',
            'query_params' => null,
            'body_template' => null,
            'items_path' => 'competitions',
            'is_enabled' => true,
            'validation_status' => 'test_passed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.providers.http-adapter.save', $providerId), [
                'loaded_endpoint_id' => $endpointId,
                'capability' => 'competitions',
                'operation' => 'list',
                'label' => 'Competizioni NAZIONI',
                'method' => 'GET',
                'endpoint' => 'competitions',
                'query_params' => '',
                'items_path' => 'competitions',
                'field_mappings' => "provider_competition_code=code\ncompetition_name=name\ncountry_name=area.name",
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('data_provider_http_endpoints', [
            'id' => $endpointId,
            'label' => 'Competizioni NAZIONI',
            'items_path' => 'competitions',
        ]);
    }

    public function test_http_adapter_mapping_rejects_fields_missing_from_contract(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.providers.http-adapter.save', $providerId), [
                'capability' => 'competitions',
                'operation' => 'list',
                'method' => 'GET',
                'endpoint' => 'competitions',
                'query_params' => '',
                'items_path' => 'competitions',
                'field_mappings' => "provider_competition_code=code\nfield_typo=name\ncompetition_name=name\ncountry_name=area.name",
            ]);

        $response->assertSessionHasErrors('field_mappings');
        $response->assertSessionHas('unknown_contract_fields', ['field_typo']);
        $response->assertSessionHas('http_adapter_test_input');
        $this->assertDatabaseMissing('data_provider_http_endpoints', [
            'data_provider_id' => $providerId,
            'capability' => 'competitions',
            'operation' => 'list',
        ]);
    }

    public function test_unknown_contract_field_can_be_added_from_http_adapter_page(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.providers.contract-fields.store', $providerId), [
                'capability' => 'competitions',
                'operation' => 'list',
                'field_key' => 'country_logo_url',
                'label' => 'Logo paese',
                'description' => 'Logo o bandiera del paese restituito dal provider.',
                'data_type' => 'url',
                'sort_order' => 80,
            ]);

        $response->assertRedirect();
        $response->assertRedirect(route('admin.providers.http-adapter.configure', $providerId));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('data_provider_contract_fields', [
            'capability' => 'competitions',
            'operation' => 'list',
            'field_key' => 'country_logo_url',
            'label' => 'Logo paese',
            'data_type' => 'url',
            'is_required' => 0,
            'sort_order' => 80,
        ]);
    }

    public function test_contract_fields_are_scoped_by_capability_and_operation(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.providers.contract-fields.store', $providerId), [
                'capability' => 'competitions',
                'operation' => 'detail',
                'field_key' => 'provider_competition_code',
                'label' => 'Codice competizione dettaglio',
                'description' => 'Codice competizione usato nella operation detail.',
                'data_type' => 'string',
                'is_required' => 1,
                'sort_order' => 10,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('data_provider_contract_fields', [
            'capability' => 'competitions',
            'operation' => 'list',
            'field_key' => 'provider_competition_code',
            'label' => 'Chiave competizione provider',
        ]);

        $this->assertDatabaseHas('data_provider_contract_fields', [
            'capability' => 'competitions',
            'operation' => 'detail',
            'field_key' => 'provider_competition_code',
            'label' => 'Codice competizione dettaglio',
        ]);
    }

    public function test_contract_field_key_is_normalized_when_created_from_payload_camel_case(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.providers.contract-fields.store', $providerId), [
                'capability' => 'competitions',
                'operation' => 'detail',
                'field_key' => 'startDate',
                'label' => 'Data inizio stagione',
                'description' => 'Data di inizio della stagione corrente restituita dal provider.',
                'data_type' => 'date',
                'is_required' => 1,
                'sort_order' => 110,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Campo contratto start_date aggiunto. Nota: startDate e stato normalizzato in start_date.');

        $this->assertDatabaseHas('data_provider_contract_fields', [
            'capability' => 'competitions',
            'operation' => 'detail',
            'field_key' => 'start_date',
            'label' => 'Data inizio stagione',
            'data_type' => 'date',
            'is_required' => 1,
            'sort_order' => 110,
        ]);
    }

    public function test_contract_field_can_be_updated_from_http_adapter_page(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.providers.contract-fields.update', [$providerId, 'competition_logo_url']), [
                'capability' => 'competitions',
                'operation' => 'list',
                'label' => 'Emblema competizione',
                'description' => 'URL dell emblema ufficiale della competizione.',
                'data_type' => 'url',
                'is_required' => 1,
                'sort_order' => 75,
            ]);

        $response->assertRedirect();
        $response->assertRedirect(route('admin.providers.http-adapter.configure', $providerId));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('data_provider_contract_fields', [
            'capability' => 'competitions',
            'operation' => 'list',
            'field_key' => 'competition_logo_url',
            'label' => 'Emblema competizione',
            'description' => 'URL dell emblema ufficiale della competizione.',
            'data_type' => 'url',
            'is_required' => 1,
            'sort_order' => 75,
        ]);
    }

    public function test_contract_field_action_url_redirects_back_to_http_adapter_on_get(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.providers.contract-fields.show', [$providerId, 'competition_logo_url']))
            ->assertRedirect(route('admin.providers.http-adapter.configure', $providerId));
    }

    public function test_unused_contract_field_can_be_deleted_from_http_adapter_page(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_contract_fields')->insert([
            'capability' => 'competitions',
            'operation' => 'detail',
            'field_key' => 'temporary_field',
            'label' => 'Temporary Field',
            'description' => 'Campo creato per prova.',
            'data_type' => 'string',
            'is_required' => false,
            'sort_order' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.providers.contract-fields.destroy', [$providerId, 'temporary_field']), [
                'capability' => 'competitions',
                'operation' => 'detail',
            ])
            ->assertRedirect(route('admin.providers.http-adapter.configure', $providerId))
            ->assertSessionHas('status', 'Campo contratto temporary_field eliminato.');

        $this->assertDatabaseMissing('data_provider_contract_fields', [
            'capability' => 'competitions',
            'operation' => 'detail',
            'field_key' => 'temporary_field',
        ]);
    }

    public function test_contract_field_delete_is_blocked_when_field_is_used_by_saved_mapping(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_contract_fields')->insert([
            'capability' => 'competitions',
            'operation' => 'detail',
            'field_key' => 'competition_name',
            'label' => 'Nome competizione',
            'description' => 'Nome leggibile della competizione.',
            'data_type' => 'string',
            'is_required' => true,
            'sort_order' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $endpointId = DB::table('data_provider_http_endpoints')->insertGetId([
            'data_provider_id' => $providerId,
            'capability' => 'competitions',
            'operation' => 'detail',
            'method' => 'GET',
            'endpoint' => 'competitions/SA',
            'query_params' => null,
            'body_template' => null,
            'items_path' => null,
            'is_enabled' => true,
            'validation_status' => 'test_passed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_payload_mappings')->insert([
            'data_provider_http_endpoint_id' => $endpointId,
            'field_mappings' => json_encode([
                'competition_name' => 'name',
            ]),
            'required_fields' => json_encode(['competition_name']),
            'validation_status' => 'mapping_validated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.providers.contract-fields.destroy', [$providerId, 'competition_name']), [
                'capability' => 'competitions',
                'operation' => 'detail',
            ])
            ->assertSessionHasErrors('contract_field');

        $this->assertDatabaseHas('data_provider_contract_fields', [
            'capability' => 'competitions',
            'operation' => 'detail',
            'field_key' => 'competition_name',
        ]);
    }

    public function test_mapping_with_new_field_can_be_saved_after_field_is_added_to_contract(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'capability' => 'competitions',
            'operation' => 'list',
            'method' => 'GET',
            'endpoint' => 'competitions',
            'query_params' => '',
            'items_path' => 'competitions',
            'field_mappings' => "provider_competition_code=code\ncompetition_name=name\ncountry_name=area.name\ncountry_logo_url=area.flag",
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.providers.http-adapter.save', $providerId), $payload)
            ->assertSessionHasErrors('field_mappings')
            ->assertSessionHas('unknown_contract_fields', ['country_logo_url']);

        $this->actingAs($this->admin)
            ->post(route('admin.providers.contract-fields.store', $providerId), [
                'capability' => 'competitions',
                'operation' => 'list',
                'field_key' => 'country_logo_url',
                'label' => 'Logo paese',
                'description' => 'Bandiera o logo del paese.',
                'data_type' => 'url',
                'sort_order' => 80,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('admin.providers.http-adapter.save', $providerId), $payload)
            ->assertSessionHasNoErrors();

        $endpoint = DB::table('data_provider_http_endpoints')
            ->where('data_provider_id', $providerId)
            ->where('capability', 'competitions')
            ->where('operation', 'list')
            ->first();

        $this->assertNotNull($endpoint);

        $mapping = DB::table('data_provider_payload_mappings')
            ->where('data_provider_http_endpoint_id', $endpoint->id)
            ->first();

        $this->assertNotNull($mapping);
        $this->assertSame('area.flag', json_decode($mapping->field_mappings, true)['country_logo_url']);
    }

    public function test_http_adapter_allows_multiple_operations_for_same_capability(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->copyCompetitionContractFieldsToOperation('detail');

        $basePayload = [
            'capability' => 'competitions',
            'method' => 'GET',
            'query_params' => '',
            'field_mappings' => "provider_competition_code=code\nprovider_competition_id=id\nprovider_area_id=area.id\ncompetition_name=name\ncountry_name=area.name",
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.providers.http-adapter.save', $providerId), array_merge($basePayload, [
                'operation' => 'list',
                'endpoint' => 'competitions',
                'items_path' => 'competitions',
            ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('admin.providers.http-adapter.save', $providerId), array_merge($basePayload, [
                'operation' => 'detail',
                'endpoint' => 'competitions/SA',
                'items_path' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('data_provider_http_endpoints', [
            'data_provider_id' => $providerId,
            'capability' => 'competitions',
            'operation' => 'list',
            'endpoint' => 'competitions',
            'items_path' => 'competitions',
        ]);

        $this->assertDatabaseHas('data_provider_http_endpoints', [
            'data_provider_id' => $providerId,
            'capability' => 'competitions',
            'operation' => 'detail',
            'endpoint' => 'competitions/SA',
            'items_path' => null,
        ]);

        $this->assertSame(2, DB::table('data_provider_http_endpoints')
            ->where('data_provider_id', $providerId)
            ->where('capability', 'competitions')
            ->count());
    }

    public function test_http_adapter_page_starts_with_empty_form_when_saved_mapping_exists(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $endpointId = DB::table('data_provider_http_endpoints')->insertGetId([
            'data_provider_id' => $providerId,
            'capability' => 'competitions',
            'operation' => 'list',
            'method' => 'GET',
            'endpoint' => 'competitions/saved-default',
            'query_params' => null,
            'body_template' => null,
            'items_path' => 'competitions',
            'is_enabled' => true,
            'validation_status' => 'saved_not_tested',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_payload_mappings')->insert([
            'data_provider_http_endpoint_id' => $endpointId,
            'field_mappings' => json_encode([
                'provider_competition_code' => 'code',
                'provider_competition_id' => 'id',
                'provider_area_id' => 'area.id',
                'competition_name' => 'name',
                'country_name' => 'area.name',
                'country_code' => 'area.code',
                'competition_type' => 'type',
                'competition_logo_url' => 'emblem',
            ]),
            'required_fields' => json_encode(['provider_competition_code', 'competition_name', 'country_name']),
            'validation_status' => 'mapping_validated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.providers.http-adapter.configure', $providerId))
            ->assertOk()
            ->assertSee('competitions/saved-default')
            ->assertSee('Nuova configurazione')
            ->assertSee('name="endpoint" x-ref="endpoint" value=""', false)
            ->assertDontSee('provider_competition_code=code')
            ->assertDontSee('country_name=area.name');
    }

    public function test_http_adapter_page_lists_saved_calls_above_the_form(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $endpointId = DB::table('data_provider_http_endpoints')->insertGetId([
            'data_provider_id' => $providerId,
            'capability' => 'competitions',
            'operation' => 'by_competition',
            'label' => 'Classifica per competizione',
            'method' => 'GET',
            'endpoint' => 'competitions/{provider_competition_code}/standings',
            'query_params' => json_encode(['season' => '{season_year}']),
            'body_template' => null,
            'items_path' => 'standings.0.table',
            'is_enabled' => true,
            'validation_status' => 'test_passed',
            'last_status_code' => 200,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_payload_mappings')->insert([
            'data_provider_http_endpoint_id' => $endpointId,
            'field_mappings' => json_encode([
                'competition_name' => 'name',
            ]),
            'required_fields' => json_encode([]),
            'validation_status' => 'mapping_validated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.providers.http-adapter.configure', $providerId))
            ->assertOk()
            ->assertSee('Chiamate configurate')
            ->assertSee('Classifica per competizione')
            ->assertSee('competitions · by_competition')
            ->assertSee('competitions/{provider_competition_code}/standings')
            ->assertSee('season=%7Bseason_year%7D')
            ->assertSee('standings.0.table')
            ->assertSee('Carica nel form');
    }

    public function test_http_adapter_saved_call_can_be_loaded_into_form_by_operation(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $endpointId = DB::table('data_provider_http_endpoints')->insertGetId([
            'data_provider_id' => $providerId,
            'capability' => 'competitions',
            'operation' => 'by_competition',
            'label' => 'Classifica per competizione',
            'method' => 'GET',
            'endpoint' => 'competitions/{provider_competition_code}/standings',
            'query_params' => json_encode(['season' => '{season_year}']),
            'body_template' => null,
            'items_path' => 'standings.0.table',
            'is_enabled' => true,
            'validation_status' => 'test_passed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_payload_mappings')->insert([
            'data_provider_http_endpoint_id' => $endpointId,
            'field_mappings' => json_encode([
                'competition_name' => 'name',
            ]),
            'required_fields' => json_encode([]),
            'validation_status' => 'mapping_validated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.providers.http-adapter.configure', [
                'provider' => $providerId,
                'capability' => 'competitions',
                'operation' => 'by_competition',
            ]))
            ->assertOk()
            ->assertSee('Modifica configurazione caricata')
            ->assertSee('name="loaded_endpoint_id" value="'.$endpointId.'"', false)
            ->assertSee('value="Classifica per competizione"', false)
            ->assertSee('Campi interni ·')
            ->assertSee('Classifica per competizione')
            ->assertSee('value="competitions/{provider_competition_code}/standings"', false)
            ->assertSee('season={season_year}')
            ->assertSee('value="standings.0.table"', false)
            ->assertSee('competition_name=name');
    }

    public function test_admin_can_delete_saved_http_adapter_mapping(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $endpointId = DB::table('data_provider_http_endpoints')->insertGetId([
            'data_provider_id' => $providerId,
            'capability' => 'competitions',
            'operation' => 'detail',
            'method' => 'GET',
            'endpoint' => 'competitions/SA',
            'query_params' => null,
            'body_template' => null,
            'items_path' => null,
            'is_enabled' => true,
            'validation_status' => 'test_passed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_payload_mappings')->insert([
            'data_provider_http_endpoint_id' => $endpointId,
            'field_mappings' => json_encode([
                'provider_competition_code' => 'code',
                'competition_name' => 'name',
                'country_name' => 'area.name',
            ]),
            'required_fields' => json_encode(['provider_competition_code', 'competition_name', 'country_name']),
            'validation_status' => 'mapping_validated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.providers.http-adapter.destroy', [$providerId, $endpointId]))
            ->assertRedirect(route('admin.providers.http-adapter.configure', $providerId))
            ->assertSessionHas('status', 'Mapping competitions · detail eliminato.');

        $this->assertDatabaseMissing('data_provider_http_endpoints', [
            'id' => $endpointId,
        ]);

        $this->assertDatabaseMissing('data_provider_payload_mappings', [
            'data_provider_http_endpoint_id' => $endpointId,
        ]);
    }

    public function test_provider_management_shows_only_http_mapping_count(): void
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
            'retry_times' => 3,
            'retry_sleep_ms' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_http_endpoints')->insert([
            'data_provider_id' => $providerId,
            'capability' => 'competitions',
            'operation' => 'list',
            'method' => 'GET',
            'endpoint' => 'competitions',
            'items_path' => 'competitions',
            'is_enabled' => true,
            'validation_status' => 'saved_not_tested',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.providers.index'))
            ->assertOk()
            ->assertSee('HTTP mapping:')
            ->assertDontSee('Mapping leghe:')
            ->assertDontSee('Mapping: 0');
    }

    public function test_provider_management_shows_saved_http_mapping_details(): void
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
            'retry_times' => 3,
            'retry_sleep_ms' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $endpointId = DB::table('data_provider_http_endpoints')->insertGetId([
            'data_provider_id' => $providerId,
            'capability' => 'competitions',
            'operation' => 'by_competition',
            'label' => 'Classifica per competizione',
            'method' => 'GET',
            'endpoint' => 'competitions/{provider_competition_code}/standings',
            'query_params' => json_encode(['season' => '{season_year}']),
            'items_path' => 'standings.0.table',
            'is_enabled' => true,
            'validation_status' => 'test_passed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_payload_mappings')->insert([
            'data_provider_http_endpoint_id' => $endpointId,
            'field_mappings' => json_encode([
                'competition_name' => 'name',
            ]),
            'required_fields' => json_encode([]),
            'validation_status' => 'mapping_validated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.providers.index'))
            ->assertOk()
            ->assertSee('Classifica per competizione')
            ->assertSee('competitions · by_competition')
            ->assertSee('competitions/{provider_competition_code}/standings')
            ->assertSee('season=%7Bseason_year%7D')
            ->assertSee('Items:')
            ->assertSee('standings.0.table')
            ->assertSee('Elimina configurazione');
    }

    public function test_provider_without_runtime_configuration_cannot_be_activated(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'pending_provider',
            'name' => 'Pending Provider',
            'base_url' => 'https://api.pending.example',
            'active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_runtime_configs')->insert([
            'data_provider_id' => $providerId,
            'is_enabled' => false,
            'priority' => 50,
            'role' => 'fallback',
            'base_url' => 'https://api.pending.example',
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry_times' => 3,
            'retry_sleep_ms' => 500,
            'metadata' => json_encode(['onboarding_state' => 'configure_runtime']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->patch(route('admin.providers.toggle', $providerId));

        $response->assertSessionHasErrors('provider');
        $this->assertDatabaseHas('data_provider_runtime_configs', [
            'data_provider_id' => $providerId,
            'is_enabled' => 0,
        ]);
    }

    public function test_credential_rotation_uses_database_configured_key(): void
    {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'test_provider',
            'name' => 'Test Provider',
            'base_url' => 'https://api.test-provider.example',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_configurations')->insert([
            'data_provider_id' => $providerId,
            'key' => 'credential_key',
            'value' => 'api_token',
            'value_type' => 'string',
            'environment' => null,
            'is_secret' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_runtime_configs')->insert([
            'data_provider_id' => $providerId,
            'is_enabled' => true,
            'priority' => 10,
            'role' => 'primary',
            'base_url' => 'https://api.test-provider.example',
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry_times' => 3,
            'retry_sleep_ms' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.providers.credentials.rotate', $providerId),
            ['credential_value' => 'rotated-secret']
        );

        $response->assertSessionHasNoErrors();

        $credential = DB::table('data_provider_credentials')->where('data_provider_id', $providerId)->first();
        $this->assertNotNull($credential);
        $this->assertSame('api_token', $credential->credential_key);
        $this->assertSame('rotated-secret', Crypt::decryptString($credential->encrypted_value));
    }

    private function copyCompetitionContractFieldsToOperation(string $operation): void
    {
        DB::table('data_provider_contract_fields')
            ->where('capability', 'competitions')
            ->where('operation', 'list')
            ->orderBy('sort_order')
            ->get()
            ->each(function (object $field) use ($operation): void {
                DB::table('data_provider_contract_fields')->insertOrIgnore([
                    'capability' => 'competitions',
                    'operation' => $operation,
                    'field_key' => $field->field_key,
                    'label' => $field->label,
                    'description' => $field->description,
                    'data_type' => $field->data_type,
                    'is_required' => $field->is_required,
                    'sort_order' => $field->sort_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }
}
