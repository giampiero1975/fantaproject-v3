<?php

namespace App\Console\Commands;

use Database\Seeders\DataProvidersSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BootstrapProviderRuntimeConfiguration extends Command
{
    protected $signature = 'providers:bootstrap-runtime {--force : Overwrite existing runtime settings and credentials}';

    protected $description = 'Import current provider runtime configuration and credentials into the database.';

    public function handle(): int
    {
        $this->callSilent('db:seed', [
            '--class' => DataProvidersSeeder::class,
            '--force' => true,
        ]);

        $definitions = [
            'football_data' => [
                'base_url' => (string) config('football_data.base_url'),
                'timeout' => (int) config('football_data.timeout', 30),
                'connect_timeout' => (int) config('football_data.connect_timeout', 10),
                'retry_times' => (int) config('football_data.retry_times', 3),
                'retry_sleep_ms' => (int) config('football_data.retry_sleep_ms', 500),
                'priority' => 10,
                'role' => 'primary',
                'credentials' => ['token' => (string) config('football_data.token')],
            ],
            'api_football' => [
                'base_url' => (string) config('api_football.base_url'),
                'timeout' => (int) config('api_football.timeout', 30),
                'connect_timeout' => (int) config('api_football.connect_timeout', 10),
                'retry_times' => (int) config('api_football.retry_times', 3),
                'retry_sleep_ms' => (int) config('api_football.retry_sleep_ms', 500),
                'priority' => 20,
                'role' => 'fallback',
                'credentials' => ['api_key' => (string) config('api_football.key')],
            ],
        ];

        $environment = app()->environment();
        $force = (bool) $this->option('force');

        DB::transaction(function () use ($definitions, $environment, $force): void {
            foreach ($definitions as $code => $definition) {
                $provider = DB::table('data_providers')->where('code', $code)->first();
                if (! $provider) {
                    throw new RuntimeException("Provider {$code} could not be created in data_providers.");
                }

                $runtimeExists = DB::table('data_provider_runtime_configs')
                    ->where('data_provider_id', $provider->id)
                    ->exists();

                if (! $runtimeExists || $force) {
                    DB::table('data_provider_runtime_configs')->updateOrInsert(
                        ['data_provider_id' => $provider->id],
                        [
                            'is_enabled' => true,
                            'priority' => $definition['priority'],
                            'role' => $definition['role'],
                            'base_url' => $definition['base_url'],
                            'timeout' => $definition['timeout'],
                            'connect_timeout' => $definition['connect_timeout'],
                            'retry_times' => $definition['retry_times'],
                            'retry_sleep_ms' => $definition['retry_sleep_ms'],
                            'updated_at' => now(),
                            'created_at' => now(),
                        ],
                    );
                }

                foreach ($definition['credentials'] as $key => $value) {
                    if ($value === '') {
                        $this->components->warn("Credential {$key} for {$code} is empty and was not imported.");
                        continue;
                    }

                    $credentialExists = DB::table('data_provider_credentials')
                        ->where('data_provider_id', $provider->id)
                        ->where('environment', $environment)
                        ->where('credential_key', $key)
                        ->exists();

                    if (! $credentialExists || $force) {
                        DB::table('data_provider_credentials')->updateOrInsert(
                            [
                                'data_provider_id' => $provider->id,
                                'environment' => $environment,
                                'credential_key' => $key,
                            ],
                            [
                                'encrypted_value' => Crypt::encryptString($value),
                                'is_active' => true,
                                'rotated_at' => now(),
                                'updated_at' => now(),
                                'created_at' => now(),
                            ],
                        );
                    }
                }
            }
        });

        $this->components->info("Provider catalog and runtime configuration imported for environment {$environment}.");
        $this->line('You may remove provider-specific values from .env only after running the verification commands successfully.');

        return self::SUCCESS;
    }
}
