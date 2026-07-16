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

    public function test_supported_provider_can_be_registered_from_catalog(): void
    {
        config()->set('data_provider_adapters.test_provider', [
            'name' => 'Test Provider',
            'credential_key' => 'api_token',
            'capabilities' => ['competitions', 'seasons'],
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.providers.store'), [
            'code' => 'test_provider',
            'base_url' => 'https://api.test-provider.example',
            'role' => 'fallback',
            'priority' => 30,
            'plan' => 'Basic',
            'credential_value' => 'secret-token',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');

        $provider = DB::table('data_providers')->where('code', 'test_provider')->first();
        $this->assertNotNull($provider);
        $this->assertSame('Test Provider', $provider->name);

        $this->assertDatabaseHas('data_provider_runtime_configs', [
            'data_provider_id' => $provider->id,
            'is_enabled' => 1,
            'priority' => 30,
            'role' => 'fallback',
            'plan' => 'Basic',
        ]);

        $credential = DB::table('data_provider_credentials')
            ->where('data_provider_id', $provider->id)
            ->first();

        $this->assertNotNull($credential);
        $this->assertSame('api_token', $credential->credential_key);
        $this->assertSame('secret-token', Crypt::decryptString($credential->encrypted_value));
    }

    public function test_provider_without_installed_adapter_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('admin.providers.index'))
            ->post(route('admin.providers.store'), [
                'code' => 'unknown_provider',
                'base_url' => 'https://api.unknown.example',
                'role' => 'primary',
                'priority' => 10,
                'plan' => 'Pro',
                'credential_value' => 'secret',
            ]);

        $response->assertRedirect(route('admin.providers.index'));
        $response->assertSessionHasErrors('code');
        $this->assertDatabaseMissing('data_providers', ['code' => 'unknown_provider']);
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
            [
                'credential_key' => 'malicious_override',
                'credential_value' => 'rotated-secret',
            ]
        );

        $response->assertSessionHasNoErrors();

        $credential = DB::table('data_provider_credentials')
            ->where('data_provider_id', $providerId)
            ->first();

        $this->assertNotNull($credential);
        $this->assertSame('api_token', $credential->credential_key);
        $this->assertSame('rotated-secret', Crypt::decryptString($credential->encrypted_value));
        $this->assertDatabaseMissing('data_provider_credentials', [
            'data_provider_id' => $providerId,
            'credential_key' => 'malicious_override',
        ]);
    }
}
