<?php

namespace App\Support\Providers;

use App\Services\Providers\ProviderAdapterDefinitionRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ProviderStatusTable
{
    /**
     * @return array<int, array<int, string>>
     */
    public function rows(): array
    {
        $catalog = app(ProviderAdapterDefinitionRepository::class)->installed();
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

        return $this->codes($catalog, $registered)
            ->map(function (string $code) use ($catalog, $registered): array {
                $adapter = $catalog->get($code);
                $provider = $registered->get($code);
                $adapterInstalled = is_array($adapter);

                return [
                    $code,
                    $adapter['name'] ?? $provider->name ?? $code,
                    $provider ? 'YES' : 'NO',
                    $adapterInstalled ? 'YES' : 'NO',
                    $this->runtime($provider),
                    $this->state($adapterInstalled, $provider),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $catalog
     * @param  Collection<string, object>  $registered
     * @return Collection<int, string>
     */
    private function codes(Collection $catalog, Collection $registered): Collection
    {
        return $catalog
            ->keys()
            ->merge($registered->keys())
            ->unique()
            ->sort()
            ->values();
    }

    private function runtime(?object $provider): string
    {
        if (! $provider) {
            return '-';
        }

        return (bool) $provider->active && (bool) $provider->is_enabled
            ? 'ACTIVE'
            : 'DISABLED';
    }

    private function state(bool $adapterInstalled, ?object $provider): string
    {
        if (! $provider) {
            return 'AVAILABLE TO REGISTER';
        }

        if (! $adapterInstalled) {
            return 'ADAPTER REQUIRED';
        }

        return (bool) $provider->active && (bool) $provider->is_enabled
            ? 'READY'
            : 'DISABLED';
    }
}
