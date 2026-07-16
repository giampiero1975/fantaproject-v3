<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ListProviderAdaptersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_status_lists_ready_adapter_required_and_available_to_register_states(): void
    {
        $this->insertProvider(
            code: 'football_data',
            name: 'football-data.org',
            active: true,
            runtimeEnabled: true
        );

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
                'capabilities' => ['competitions', 'seasons', 'teams'],
                'credential_required' => false,
                'credential_key' => null,
                'adapter_supported' => false,
                'onboarding_state' => 'adapter_required',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('providers:status')
            ->expectsTable(
                ['Code', 'Provider', 'Registered', 'Adapter installed', 'Runtime', 'State'],
                [
                    ['api_football', 'API-Football', 'NO', 'YES', '-', 'AVAILABLE TO REGISTER'],
                    ['football_data', 'football-data.org', 'YES', 'YES', 'ACTIVE', 'READY'],
                    ['thesportsdb', 'TheSportsDB', 'YES', 'NO', 'DISABLED', 'ADAPTER REQUIRED'],
                ]
            )
            ->assertSuccessful();
    }

    public function test_provider_adapters_command_remains_a_compatible_alias(): void
    {
        $this->artisan('providers:adapters')
            ->expectsTable(
                ['Code', 'Provider', 'Registered', 'Adapter installed', 'Runtime', 'State'],
                [
                    ['api_football', 'API-Football', 'NO', 'YES', '-', 'AVAILABLE TO REGISTER'],
                    ['football_data', 'football-data.org', 'NO', 'YES', '-', 'AVAILABLE TO REGISTER'],
                ]
            )
            ->assertSuccessful();
    }

    private function insertProvider(
        string $code,
        string $name,
        bool $active,
        bool $runtimeEnabled,
    ): int {
        $providerId = DB::table('data_providers')->insertGetId([
            'code' => $code,
            'name' => $name,
            'base_url' => 'https://api.example.test/'.$code,
            'active' => $active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_runtime_configs')->insert([
            'data_provider_id' => $providerId,
            'is_enabled' => $runtimeEnabled,
            'priority' => 30,
            'role' => 'fallback',
            'base_url' => 'https://api.example.test/'.$code,
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry_times' => 3,
            'retry_sleep_ms' => 500,
            'plan' => 'Free',
            'metadata' => json_encode([
                'capabilities' => ['competitions', 'seasons', 'teams'],
                'adapter_supported' => true,
                'onboarding_state' => 'ready',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $providerId;
    }
}
