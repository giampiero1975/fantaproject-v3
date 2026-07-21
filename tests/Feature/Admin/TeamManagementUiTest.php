<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class TeamManagementUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_management_page_renders_step_two_controls_and_filters(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');

        $leagueSeasonId = $this->seedLeagueSeason();

        $teamId = DB::table('teams')->insertGetId([
            'country_id' => 1,
            'name' => 'Juventus',
            'short_name' => 'Juve',
            'code' => 'JUV',
            'crest_url' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('league_season_teams')->insert([
            'league_season_id' => $leagueSeasonId,
            'team_id' => $teamId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.teams.index'))
            ->assertOk()
            ->assertSee('Squadre')
            ->assertSee('Step 2')
            ->assertSee('Copertura squadre')
            ->assertSee('Stagioni con squadre')
            ->assertSee('Squadre sincronizzate')
            ->assertSee('Presenze stagionali')
            ->assertSee('Analizza squadre stagione')
            ->assertSee('Italy')
            ->assertSee('Serie A')
            ->assertSee('2026/27')
            ->assertSee('Juventus')
            ->assertSee('JUV')
            ->assertSee('coperta');

        $view = file_get_contents(resource_path('views/admin/teams/index.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('aria-label="Filtra copertura squadre"', $view);
        $this->assertStringContainsString('data-team-search-filter', $view);
        $this->assertStringContainsString('data-team-country-filter', $view);
        $this->assertStringContainsString('data-team-competition-filter', $view);
        $this->assertStringContainsString('data-team-status-filter', $view);
        $this->assertStringContainsString('data-team-filter-reset', $view);
        $this->assertStringContainsString('data-team-coverage-row', $view);
        $this->assertStringContainsString('data-team-registry', $view);
        $this->assertStringContainsString('aria-label="Filtra squadre sincronizzate"', $view);
        $this->assertStringContainsString('data-team-registry-search-filter', $view);
        $this->assertStringContainsString('data-team-registry-country-filter', $view);
        $this->assertStringContainsString('data-team-registry-competition-filter', $view);
        $this->assertStringContainsString('data-team-registry-season-filter', $view);
        $this->assertStringContainsString('data-team-registry-status-filter', $view);
        $this->assertStringContainsString('data-team-registry-row', $view);
        $this->assertStringContainsString("route('admin.teams.analyze')", $view);
        $this->assertStringContainsString("route('admin.teams.apply')", $view);
        $this->assertStringContainsString('fanta-oracle:team-coverage-filters', $view);
        $this->assertStringContainsString('fanta-oracle:team-registry-filters', $view);
        $this->assertStringNotContainsString('team_provider_mappings', $view);
        $this->assertStringNotContainsString('provider_count', $view);
    }

    private function seedLeagueSeason(): int
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

        return DB::table('league_seasons')->insertGetId([
            'league_id' => $leagueId,
            'season_id' => $seasonId,
            'is_current' => true,
            'status' => 'active',
            'start_date' => null,
            'end_date' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}