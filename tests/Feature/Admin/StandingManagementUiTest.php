<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class StandingManagementUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_standing_management_page_renders_step_three_controls_and_filters(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');

        $leagueSeasonTeamId = $this->seedStanding();

        DB::table('league_season_team_standings')->insert([
            'league_season_team_id' => $leagueSeasonTeamId,
            'position' => 1,
            'played_games' => 38,
            'points' => 83,
            'goal_difference' => 40,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.standings.index'))
            ->assertOk()
            ->assertSee('Classifiche')
            ->assertSee('Step 3')
            ->assertSee('Copertura classifiche')
            ->assertSee('Classifiche sincronizzate')
            ->assertSee('Analizza classifica stagione')
            ->assertSee('Juventus')
            ->assertSee('Serie A')
            ->assertSee('2026/27')
            ->assertSee('83');

        $view = file_get_contents(resource_path('views/admin/standings/index.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('aria-label="Filtra copertura classifiche"', $view);
        $this->assertStringContainsString('data-standing-search-filter', $view);
        $this->assertStringContainsString('data-standing-country-filter', $view);
        $this->assertStringContainsString('data-standing-competition-filter', $view);
        $this->assertStringContainsString('data-standing-status-filter', $view);
        $this->assertStringContainsString('data-standing-coverage-row', $view);
        $this->assertStringContainsString("route('admin.standings.analyze')", $view);
        $this->assertStringContainsString("route('admin.standings.apply')", $view);
        $this->assertStringContainsString('fanta-oracle:standing-coverage-filters', $view);
        $this->assertStringNotContainsString('team_provider_mappings', $view);
        $this->assertStringNotContainsString('provider_count', $view);
    }

    private function seedStanding(): int
    {
        $confederationId = DB::table('confederations')->insertGetId(['code' => 'UEFA', 'name' => 'UEFA', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $countryId = DB::table('countries')->insertGetId(['confederation_id' => $confederationId, 'region' => 'Europe', 'name' => 'Italy', 'iso2' => 'IT', 'iso3' => 'ITA', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $leagueId = DB::table('leagues')->insertGetId(['country_id' => $countryId, 'name' => 'Serie A', 'slug' => 'serie-a', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $seasonId = DB::table('seasons')->insertGetId(['season_key' => 2026, 'label' => '2026/27', 'created_at' => now(), 'updated_at' => now()]);
        $leagueSeasonId = DB::table('league_seasons')->insertGetId(['league_id' => $leagueId, 'season_id' => $seasonId, 'is_current' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $teamId = DB::table('teams')->insertGetId(['country_id' => $countryId, 'name' => 'Juventus', 'code' => 'JUV', 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('league_season_teams')->insertGetId(['league_season_id' => $leagueSeasonId, 'team_id' => $teamId, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
}