<?php

namespace App\Console\Commands;

use App\Services\Providers\ProviderConfigurationWriter;
use Database\Seeders\DataProvidersSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BootstrapProviderRuntimeConfiguration extends Command
{
    protected $signature = 'providers:bootstrap-runtime {--force : Overwrite existing runtime settings}';

    protected $description = 'Ensure provider catalog and default runtime configuration exist in the database.';

    public function handle(): int
    {
        $this->callSilent('db:seed', [
            '--class' => DataProvidersSeeder::class,
            '--force' => true,
        ]);

        $definitions = [
            'football_data' => [
                'base_url' => 'https://api.football-data.org/v4',
                'timeout' => 30,
                'connect_timeout' => 10,
                'retry_times' => 3,
                'retry_sleep_ms' => 500,
                'priority' => 10,
                'role' => 'primary',
            ],
            'api_football' => [
                'base_url' => 'https://v3.football.api-sports.io',
                'timeout' => 30,
                'connect_timeout' => 10,
                'retry_times' => 3,
                'retry_sleep_ms' => 500,
                'priority' => 20,
                'role' => 'fallback',
            ],
        ];

        $force = (bool) $this->option('force');

        DB::transaction(function () use ($definitions, $force): void {
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

                app(ProviderConfigurationWriter::class)->writeMany((int) $provider->id, [
                    'base_url' => $definition['base_url'],
                    'timeout' => $definition['timeout'],
                    'connect_timeout' => $definition['connect_timeout'],
                    'retry_times' => $definition['retry_times'],
                    'retry_sleep_ms' => $definition['retry_sleep_ms'],
                    'priority' => $definition['priority'],
                    'role' => $definition['role'],
                ]);
            }
        });

        $this->components->info('Provider catalog and runtime configuration are stored in the database.');
        $this->line('Credentials are managed only through data_provider_credentials/UI, not provider-specific config files.');

        return self::SUCCESS;
    }
}
