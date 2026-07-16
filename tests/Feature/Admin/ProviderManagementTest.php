<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
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

    public function test_supported_provider_can_be_registered_and_activated(): void
    {
        config()->set('data_provider_adapters.test_provider', [
            'name' => 'Test Provider',
            'credential_key' => 'api_token',
            'capabilities' => ['competitions', 'seasons'],
        ]);

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
        $this->assertTrue((bool) $provider->active);

        $this->assertDatabaseHas('data_provider_runtime_configs', [
            'data_provider_id' => $provider->id,
            'is_enabled' => 1,
            'priority' => 30,
            'role' => 'fallback',
            'plan' => 'Basic',
        ]);

        $credential = DB::table('data_provider_credentials')->where('data_provider_id', $provider->id)->first();
        $this->assertNotNull($credential);
        $this->assertSame('api_token', $credential->credential_key);
        $this->assertSame('secret-token', Crypt::decryptString($credential->encrypted_value));
    }

    public function test_provider_without_adapter_can_be_registered_from_ui_without_credential(): void
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
        $this->assertSame('adapter_required', $metadata['onboarding_state']);
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

    public function test_provider_management_marks_registered_provider_without_adapter(): void
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
                'adapter_supported' => false,
                'onboarding_state' => 'adapter_required',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.providers.index'))
            ->assertOk()
            ->assertSee('TheSportsDB')
            ->assertSee('Adapter richiesto')
            ->assertSee('Installa adapter per attivare');
    }

    public function test_provider_management_offers_installed_adapters_to_configure_from_ui(): void
    {
        config()->set('data_provider_adapters', [
            'api_football' => [
                'name' => 'API-Football',
                'credential_key' => 'api_key',
                'capabilities' => ['competitions', 'seasons', 'teams'],
            ],
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.providers.index'))
            ->assertOk()
            ->assertSee('Adapter installato disponibile')
            ->assertSee('API-Football')
            ->assertSee('data-installed-adapter', false)
            ->assertSee('data-code="api_football"', false)
            ->assertSee('data-credential-key="api_key"', false);
    }

    public function test_provider_without_adapter_cannot_be_activated(): void
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
            'metadata' => json_encode(['adapter_supported' => false]),
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

    public function test_credential_rotation_uses_adapter_defined_key(): void
    {
        config()->set('data_provider_adapters.test_provider', [
            'name' => 'Test Provider',
            'credential_key' => 'api_token',
            'capabilities' => ['competitions'],
        ]);

        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'test_provider',
            'name' => 'Test Provider',
            'base_url' => 'https://api.test-provider.example',
            'active' => true,
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
}
