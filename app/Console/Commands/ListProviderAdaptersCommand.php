<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ListProviderAdaptersCommand extends Command
{
    protected $signature = 'providers:adapters';

    protected $description = 'List provider adapter catalog and DB registration state';

    public function handle(): int
    {
        $catalog = collect(config('data_provider_adapters', []));
        $registered = DB::table('data_providers as p')
            ->leftJoin('data_provider_runtime_configs as rc', 'rc.data_provider_id', '=', 'p.id')
            ->get([
                'p.code',
                'p.name',
                'p.active',
                'rc.is_enabled',
                'rc.metadata',
            ])
            ->keyBy('code');

        $codes = $catalog
            ->keys()
            ->merge($registered->keys())
            ->unique()
            ->sort()
            ->values();

        $rows = $codes
            ->map(function (string $code) use ($catalog, $registered): array {
                $adapter = $catalog->get($code);
                $provider = $registered->get($code);
                $metadata = json_decode((string) ($provider->metadata ?? ''), true) ?: [];
                $adapterInstalled = is_array($adapter);

                return [
                    $code,
                    $adapter['name'] ?? $provider->name ?? $code,
                    $adapter['credential_key'] ?? $metadata['credential_key'] ?? '—',
                    implode(', ', $adapter['capabilities'] ?? $metadata['capabilities'] ?? []),
                    $provider ? 'YES' : 'NO',
                    $this->resolveState($adapterInstalled, $provider, $metadata),
                ];
            })
            ->values()
            ->all();

        $this->table(
            ['Code', 'Provider', 'Credential', 'Capabilities', 'Registered', 'State'],
            $rows
        );

        if ($rows === []) {
            $this->warn('No provider adapters are declared and no providers are registered.');
        }

        return self::SUCCESS;
    }

    private function resolveState(bool $adapterInstalled, mixed $provider, array $metadata): string
    {
        if (! $provider) {
            return 'AVAILABLE';
        }

        if (! $adapterInstalled) {
            return strtoupper((string) ($metadata['onboarding_state'] ?? 'adapter_required'));
        }

        return (bool) $provider->active && (bool) $provider->is_enabled
            ? 'ACTIVE'
            : 'DISABLED';
    }
}
