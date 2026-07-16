<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class AvailableProviderAdaptersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_only_unregistered_adapters(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('admin');
        $admin->assignRole('admin');

        DB::table('data_providers')->insert([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.providers.available-adapters'));

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'api_football')
            ->assertJsonPath('data.0.name', 'API-Football')
            ->assertJsonPath('data.0.credential_key', 'api_key')
            ->assertJsonPath('data.0.capabilities', ['competitions', 'seasons', 'teams'])
            ->assertJsonPath('data.1.code', 'thesportsdb')
            ->assertJsonPath('data.1.name', 'TheSportsDB')
            ->assertJsonPath('data.1.credential_key', null);
    }

    public function test_registered_adapters_are_not_offered_again(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('admin');
        $admin->assignRole('admin');

        foreach (config('data_provider_adapters') as $code => $adapter) {
            DB::table('data_providers')->insert([
                'code' => $code,
                'name' => $adapter['name'],
                'base_url' => 'https://api.example.test/'.$code,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($admin)
            ->getJson(route('admin.providers.available-adapters'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_provider_without_credentials_can_be_registered(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('admin');
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post(route('admin.providers.store'), [
            'code' => 'thesportsdb',
            'base_url' => 'https://www.thesportsdb.com/api/v1/json/3',
            'role' => 'fallback',
            'priority' => 30,
            'plan' => 'Free',
            'credential_value' => null,
        ]);

        $response->assertSessionHasNoErrors();

        $provider = DB::table('data_providers')->where('code', 'thesportsdb')->first();

        $this->assertNotNull($provider);
        $this->assertSame('TheSportsDB', $provider->name);
        $this->assertDatabaseHas('data_provider_runtime_configs', [
            'data_provider_id' => $provider->id,
            'is_enabled' => 1,
            'priority' => 30,
            'role' => 'fallback',
            'plan' => 'Free',
        ]);
        $this->assertDatabaseMissing('data_provider_credentials', [
            'data_provider_id' => $provider->id,
        ]);
    }
}
