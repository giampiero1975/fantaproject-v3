<?php

namespace App\Console\Commands;

use App\Services\Providers\ProviderConfigurationWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class BootstrapProviderRuntimeConfiguration extends Command
{
    protected $signature = 'providers:bootstrap-runtime {--force : Overwrite existing runtime settings}';

    protected $description = 'Ensure provider catalog and default runtime configuration exist in the database.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $providers = DB::table('data_providers as p')
            ->leftJoin('data_provider_runtime_configs as rc', 'rc.data_provider_id', '=', 'p.id')
            ->get([
                'p.id',
                'p.base_url as provider_base_url',
                'rc.is_enabled',
                'rc.base_url as runtime_base_url',
                'rc.timeout',
                'rc.connect_timeout',
                'rc.retry_times',
                'rc.retry_sleep_ms',
                'rc.priority',
                'rc.role',
                'rc.plan',
            ]);

        DB::transaction(function () use ($providers, $force): void {
            foreach ($providers as $provider) {
                $runtimeExists = DB::table('data_provider_runtime_configs')
                    ->where('data_provider_id', $provider->id)
                    ->exists();

                if (! $runtimeExists || $force) {
                    DB::table('data_provider_runtime_configs')->updateOrInsert(
                        ['data_provider_id' => $provider->id],
                        [
                            'is_enabled' => (bool) ($provider->is_enabled ?? false),
                            'priority' => (int) ($provider->priority ?? 100),
                            'role' => (string) ($provider->role ?? 'fallback'),
                            'base_url' => (string) ($provider->runtime_base_url ?? $provider->provider_base_url),
                            'timeout' => (int) ($provider->timeout ?? 30),
                            'connect_timeout' => (int) ($provider->connect_timeout ?? 10),
                            'retry_times' => (int) ($provider->retry_times ?? 3),
                            'retry_sleep_ms' => (int) ($provider->retry_sleep_ms ?? 500),
                            'plan' => $provider->plan,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ],
                    );
                }

                app(ProviderConfigurationWriter::class)->writeMany((int) $provider->id, [
                    'base_url' => (string) ($provider->runtime_base_url ?? $provider->provider_base_url),
                    'timeout' => (int) ($provider->timeout ?? 30),
                    'connect_timeout' => (int) ($provider->connect_timeout ?? 10),
                    'retry_times' => (int) ($provider->retry_times ?? 3),
                    'retry_sleep_ms' => (int) ($provider->retry_sleep_ms ?? 500),
                    'priority' => (int) ($provider->priority ?? 100),
                    'role' => (string) ($provider->role ?? 'fallback'),
                    'plan' => $provider->plan,
                ]);
            }
        });

        $this->components->info('Provider runtime configuration synchronized from database provider rows.');
        $this->line('No provider is created by this command.');

        return self::SUCCESS;
    }
}
