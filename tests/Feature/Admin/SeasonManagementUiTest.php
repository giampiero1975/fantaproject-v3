<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeasonManagementUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_season_management_exposes_country_funnel_filter_contract(): void
    {
        $view = file_get_contents(resource_path('views/admin/seasons/index.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('aria-label="Filtra registry competizioni e provider"', $view);
        $this->assertStringContainsString('data-season-country-filter', $view);
        $this->assertStringContainsString('data-season-competition-filter', $view);
        $this->assertStringContainsString('data-season-provider-filter', $view);
        $this->assertStringContainsString('data-season-mapping-filter', $view);
        $this->assertStringContainsString('data-season-capability-filter', $view);
        $this->assertStringContainsString('data-season-filter-reset', $view);
        $this->assertStringContainsString('data-season-league-select', $view);
        $this->assertStringContainsString('data-season-registry-row', $view);
        $this->assertStringContainsString('data-season-registry-empty', $view);
        $this->assertStringContainsString('data-season-required-capabilities', $view);
        $this->assertStringContainsString('data-season-provider-capabilities', $view);
        $this->assertStringContainsString('data-season-provider-mapping-form', $view);
        $this->assertStringContainsString("route('admin.seasons.provider-mappings.store')", $view);
        $this->assertStringContainsString('Capability richieste da Gestione Stagioni', $view);
        $this->assertStringContainsString('data-country-id="{{ $league->country_id }}"', $view);
        $this->assertStringContainsString('data-competition="{{ \\Illuminate\\Support\\Str::lower($league->name) }}"', $view);
        $this->assertStringContainsString('data-provider="{{ \\Illuminate\\Support\\Str::lower($provider->provider_name) }}"', $view);
        $this->assertStringContainsString('data-mapping="{{ \\Illuminate\\Support\\Str::lower($provider->external_id) }}"', $view);
        $this->assertStringContainsString("countryFilter?.addEventListener('change', applyFilter)", $view);
        $this->assertStringContainsString("mappingFilter?.addEventListener('input', applyFilter)", $view);
        $this->assertStringContainsString("fanta-oracle:season-registry-filters", $view);
        $this->assertStringContainsString('window.localStorage.setItem(storageKey', $view);
        $this->assertStringContainsString('restoreFilters();', $view);
    }

    public function test_season_management_renders_provider_capability_matrix(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');

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

        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'provider_lab',
            'name' => 'Provider Lab',
            'base_url' => 'https://api.example.test',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_runtime_configs')->insert([
            'data_provider_id' => $providerId,
            'is_enabled' => true,
            'priority' => 10,
            'role' => 'primary',
            'base_url' => 'https://api.example.test',
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

        $competitionsEndpointId = $this->insertEndpoint($providerId, 'competitions', true, 'mapping_validated');
        $this->insertMapping($competitionsEndpointId, 'mapping_validated');

        $seasonsEndpointId = $this->insertEndpoint($providerId, 'seasons', true, 'test_passed');
        $this->insertMapping($seasonsEndpointId, 'mapping_incomplete');

        $this->actingAs($admin)
            ->get(route('admin.seasons.index'))
            ->assertOk()
            ->assertSee('Capability richieste da Gestione Stagioni')
            ->assertSee('competitions')
            ->assertSee('pronta')
            ->assertSee('seasons')
            ->assertSee('da validare')
            ->assertSee('teams')
            ->assertSee('manca');
    }

    public function test_admin_can_create_provider_mapping_from_season_management(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');

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

        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org/v4',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.seasons.provider-mappings.store'), [
                'league_id' => $leagueId,
                'data_provider_id' => $providerId,
                'external_id' => 'SA',
                'external_name' => 'Serie A',
                'external_country' => 'Italy',
            ])
            ->assertRedirect(route('admin.seasons.index'))
            ->assertSessionHas('status', 'Mapping provider collegato alla competizione interna.');

        $this->assertDatabaseHas('league_provider_mappings', [
            'league_id' => $leagueId,
            'data_provider_id' => $providerId,
            'external_id' => 'SA',
            'external_name' => 'Serie A',
            'external_country' => 'Italy',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.seasons.provider-mappings.store'), [
                'league_id' => $leagueId,
                'data_provider_id' => $providerId,
                'external_id' => 'SA',
                'external_name' => 'Serie A TIM',
                'external_country' => 'Italy',
            ])
            ->assertRedirect(route('admin.seasons.index'))
            ->assertSessionHas('status', 'Mapping provider aggiornato per la competizione interna.');

        $this->assertDatabaseHas('league_provider_mappings', [
            'league_id' => $leagueId,
            'data_provider_id' => $providerId,
            'external_id' => 'SA',
            'external_name' => 'Serie A TIM',
        ]);
    }

    private function insertEndpoint(int $providerId, string $capability, bool $enabled, string $status): int
    {
        return DB::table('data_provider_http_endpoints')->insertGetId([
            'data_provider_id' => $providerId,
            'capability' => $capability,
            'operation' => 'list',
            'method' => 'GET',
            'endpoint' => $capability,
            'items_path' => $capability,
            'is_enabled' => $enabled,
            'validation_status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertMapping(int $endpointId, string $status): void
    {
        DB::table('data_provider_payload_mappings')->insert([
            'data_provider_http_endpoint_id' => $endpointId,
            'field_mappings' => json_encode(['name' => 'name']),
            'required_fields' => json_encode(['name']),
            'validation_status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
