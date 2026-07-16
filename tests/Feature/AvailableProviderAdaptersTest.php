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

    private function admin(): User
    {
        $admin = User::factory()->create();
        Role::findOrCreate('admin');
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_admin_can_list_only_unregistered_installed_adapters(): void
    {
        DB::table('data_providers')->insert([
            'code' => 'football_data',
            'name' => 'football-data.org',
            'base_url' => 'https://api.football-data.org',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->getJson(route('admin.providers.available-adapters'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'api_football')
            ->assertJsonPath('data.0.name', 'API-Football')
            ->assertJsonPath('data.0.credential_key', 'api_key')
            ->assertJsonPath('data.0.capabilities', ['competitions', 'seasons', 'teams']);
    }

    public function test_registered_adapters_are_not_offered_again(): void
    {
        foreach (DB::table('data_provider_adapter_definitions')->get() as $adapter) {
            DB::table('data_providers')->insert([
                'code' => $adapter->code,
                'name' => $adapter->name,
                'base_url' => 'https://api.example.test/'.$adapter->code,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($this->admin())
            ->getJson(route('admin.providers.available-adapters'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_provider_without_credentials_can_be_registered_from_ui_while_adapter_is_missing(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.providers.store'), [
            'code' => 'thesportsdb',
            'name' => 'TheSportsDB',
            'base_url' => 'https://www.thesportsdb.com/api/v1/json/3',
            'role' => 'fallback',
            'priority' => 30,
            'plan' => 'Free',
            'credential_required' => '0',
            'credential_key' => null,
            'credential_value' => null,
            'capabilities' => ['competitions', 'seasons', 'teams'],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');

        $provider = DB::table('data_providers')->where('code', 'thesportsdb')->first();

        $this->assertNotNull($provider);
        $this->assertSame('TheSportsDB', $provider->name);
        $this->assertFalse((bool) $provider->active);

        $runtime = DB::table('data_provider_runtime_configs')
            ->where('data_provider_id', $provider->id)
            ->first();

        $this->assertNotNull($runtime);
        $this->assertFalse((bool) $runtime->is_enabled);
        $this->assertSame(30, (int) $runtime->priority);
        $this->assertSame('fallback', $runtime->role);
        $this->assertSame('Free', $runtime->plan);

        $metadata = json_decode((string) $runtime->metadata, true);
        $this->assertSame('adapter_required', $metadata['onboarding_state']);
        $this->assertFalse($metadata['adapter_supported']);
        $this->assertFalse($metadata['credential_required']);
        $this->assertNull($metadata['credential_key']);
        $this->assertSame(['competitions', 'seasons', 'teams'], $metadata['capabilities']);

        $this->assertDatabaseMissing('data_provider_credentials', [
            'data_provider_id' => $provider->id,
        ]);
    }
}
