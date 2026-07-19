<?php

namespace App\Support\Providers;

use Illuminate\Support\Facades\DB;

final class ProviderStatusTable
{
    /**
     * @return array<int, array<int, string>>
     */
    public function rows(): array
    {
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
        $httpConfigured = DB::table('data_provider_http_endpoints as e')
            ->join('data_providers as p', 'p.id', '=', 'e.data_provider_id')
            ->where('e.is_enabled', true)
            ->pluck('p.code')
            ->flip();

        return $registered
            ->keys()
            ->sort()
            ->values()
            ->map(function (string $code) use ($registered, $httpConfigured): array {
                $provider = $registered->get($code);
                $hasHttpAdapter = $httpConfigured->has($code);

                return [
                    $code,
                    $provider->name ?? $code,
                    $provider ? 'YES' : 'NO',
                    $hasHttpAdapter ? 'YES' : 'NO',
                    $this->runtime($provider),
                    $this->state($hasHttpAdapter, $provider),
                ];
            })
            ->values()
            ->all();
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

    private function state(bool $hasHttpAdapter, ?object $provider): string
    {
        if (! $provider) {
            return 'AVAILABLE TO REGISTER';
        }

        if ($hasHttpAdapter) {
            return (bool) $provider->active && (bool) $provider->is_enabled
                ? 'READY'
                : 'CONFIGURED';
        }

        return 'TO CONFIGURE';
    }
}
