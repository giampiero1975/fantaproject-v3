<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class TeamTierManagementUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_tier_management_page_renders_step_four_controls_and_filters(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');

        [$leagueSeasonId, $teamId] = $this->seedTierRegistry();

        DB::table('teams')->where('id', $teamId)->update([
            'tier_globale' => 2,
            'posizione_media_storica' => 8.1250,
        ]);

        DB::table('league_season_teams')
            ->where('league_season_id', $leagueSeasonId)
            ->where('team_id', $teamId)
            ->update(['tier_stagionale' => 2, 'tier_score' => 8.1250]);

        $this->actingAs($admin)
            ->get(route('admin.team-tiers.index'))
            ->assertOk()
            ->assertSee('Tier Squadre')
            ->assertSee('Step 4')
            ->assertSee('Copertura tier')
            ->assertSee('Registry tier squadre')
            ->assertSee('Analizza tier squadra')
            ->assertSee('Audit prestazione reale')
            ->assertSee('team_tier_settings')
            ->assertSee('Italy')
            ->assertSee('Serie A')
            ->assertSee('2026/27')
            ->assertSee('AC Milan')
            ->assertSee('Tier 2')
            ->assertSee('coperta');

        $view = file_get_contents(resource_path('views/admin/team-tiers/index.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('aria-label="Filtra copertura tier"', $view);
        $this->assertStringContainsString('data-tier-search-filter', $view);
        $this->assertStringContainsString('data-tier-country-filter', $view);
        $this->assertStringContainsString('data-tier-competition-filter', $view);
        $this->assertStringContainsString('data-tier-status-filter', $view);
        $this->assertStringContainsString('data-tier-coverage-row', $view);
        $this->assertStringContainsString('data-tier-registry', $view);
        $this->assertStringContainsString('aria-label="Filtra registry tier"', $view);
        $this->assertStringContainsString('data-tier-registry-search-filter', $view);
        $this->assertStringContainsString('data-tier-registry-tier-filter', $view);
        $this->assertStringContainsString('data-tier-registry-row', $view);
        $this->assertStringContainsString("route('admin.team-tiers.analyze')", $view);
        $this->assertStringContainsString("route('admin.team-tiers.audit-performance')", $view);
        $this->assertStringContainsString("route('admin.team-tiers.apply')", $view);
        $this->assertStringContainsString('fanta-oracle:${prefix}-filters', $view);
        $this->assertStringNotContainsString('data_provider', $view);
        $this->assertStringNotContainsString('provider_count', $view);
    }

    /** @return array{0:int,1:int} */
    private function seedTierRegistry(): array
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $teamId = DB::table('teams')->insertGetId([
            'country_id' => $countryId,
            'name' => 'AC Milan',
            'short_name' => 'Milan',
            'code' => 'MIL',
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

        return [$leagueSeasonId, $teamId];
    }
}


