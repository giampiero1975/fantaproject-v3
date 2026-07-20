<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class DryRunHttpProviderCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_http_provider_dry_run_uses_database_query_auth_and_mapping(): void
    {
        File::deleteDirectory(storage_path('logs/administration/provider_managment'));

        Http::fake([
            'api.soccerdataapi.test/country/*' => Http::response([
                'count' => 1,
                'results' => [
                    [
                        'id' => 201,
                        'name' => 'italy',
                    ],
                ],
            ]),
        ]);

        $providerId = DB::table('data_providers')->insertGetId([
            'code' => 'soccerdataapi',
            'name' => 'SoccerDataAPI',
            'base_url' => 'https://api.soccerdataapi.test',
            'active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_configurations')->insert([
            [
                'data_provider_id' => $providerId,
                'key' => 'auth_type',
                'value' => 'query',
                'value_type' => 'string',
                'environment' => null,
                'is_secret' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'data_provider_id' => $providerId,
                'key' => 'credential_key',
                'value' => 'auth_token',
                'value_type' => 'string',
                'environment' => null,
                'is_secret' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'data_provider_id' => $providerId,
                'key' => 'auth_query_param',
                'value' => 'auth_token',
                'value_type' => 'string',
                'environment' => null,
                'is_secret' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'data_provider_id' => $providerId,
                'key' => 'http_headers',
                'value' => json_encode(['Accept-Encoding' => 'gzip']),
                'value_type' => 'json',
                'environment' => null,
                'is_secret' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('data_provider_credentials')->insert([
            'data_provider_id' => $providerId,
            'environment' => app()->environment(),
            'credential_key' => 'auth_token',
            'encrypted_value' => Crypt::encryptString('secret-token'),
            'is_active' => true,
            'rotated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $endpointId = DB::table('data_provider_http_endpoints')->insertGetId([
            'data_provider_id' => $providerId,
            'label' => 'Nazioni',
            'capability' => 'competitions',
            'operation' => 'list',
            'method' => 'GET',
            'endpoint' => 'country/',
            'query_params' => null,
            'body_template' => null,
            'items_path' => 'results',
            'is_enabled' => true,
            'validation_status' => 'test_passed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_provider_payload_mappings')->insert([
            'data_provider_http_endpoint_id' => $endpointId,
            'field_mappings' => json_encode([
                'provider_country_id' => 'id',
                'country_name' => 'name',
            ]),
            'required_fields' => json_encode(['provider_country_id', 'country_name']),
            'validation_status' => 'mapping_validated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('providers:http-dry-run', [
            'provider' => 'soccerdataapi',
            '--capability' => 'competitions',
            '--operation' => 'list',
        ])
            ->expectsOutputToContain('HTTP provider dry-run')
            ->expectsOutputToContain('"auth_token":"***"')
            ->expectsOutputToContain('"provider_country_id": 201')
            ->assertSuccessful();

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.soccerdataapi.test/country/?auth_token=secret-token'
            && $request->hasHeader('Accept-Encoding', 'gzip'));

        $log = File::get(storage_path('logs/administration/provider_managment/http_adapter_dry_run.log'));
        $this->assertStringContainsString('[http_adapter_dry_run][info] HTTP provider dry-run started.', $log);
        $this->assertStringContainsString('query_keys', $log);
        $this->assertStringContainsString('auth_token', $log);
    }
}
