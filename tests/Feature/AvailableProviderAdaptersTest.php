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
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'api_football')
            ->assertJsonPath('data.0.name', 'API-Football')
            ->assertJsonPath('data.0.credential_key', 'api_key')
            ->assertJsonPath('data.0.capabilities', ['competitions', 'seasons', 'teams']);
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
}
