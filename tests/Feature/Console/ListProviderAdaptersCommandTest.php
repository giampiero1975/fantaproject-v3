<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ListProviderAdaptersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_registered_provider_waiting_for_an_adapter(): void
    {
        config()->set('data_provider_adapters', [
            'football_data' => [
                'name' => 'football-data.org',
                'credential_key' => 'token',
                'capabilities' => ['competitions', 'seasons', 'teams'],
            ],
        ]);

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

        $this->artisan('providers:adapters')
            ->expectsTable(
                ['Code', 'Provider', 'Credential', 'Capabilities', 'Registered', 'State'],
                [
                    ['football_data', 'football-data.org', 'token', 'competitions, seasons, teams', 'NO', 'AVAILABLE'],
                    ['thesportsdb', 'TheSportsDB', '—', 'competitions, seasons, teams', 'YES', 'ADAPTER_REQUIRED'],
                ]
            )
            ->assertSuccessful();
    }
}
