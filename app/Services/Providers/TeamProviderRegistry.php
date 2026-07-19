<?php

namespace App\Services\Providers;

use App\Contracts\Providers\TeamDataProvider;
use Illuminate\Support\Facades\DB;

final class TeamProviderRegistry
{
    public function __construct(
        private readonly HttpProviderPayloadMapper $mapper,
        private readonly ProviderHttpAuthentication $authentication,
    ) {}

    /** @return list<TeamDataProvider> */
    public function all(): array
    {
        return collect($this->providerRows())
            ->map(fn (object $provider): ?TeamDataProvider => $this->providerFromRow($provider))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<object>
     */
    private function providerRows(): array
    {
        return DB::table('data_providers as p')
            ->leftJoin('data_provider_runtime_configs as rc', 'rc.data_provider_id', '=', 'p.id')
            ->leftJoin('data_provider_http_endpoints as e', function ($join): void {
                $join->on('e.data_provider_id', '=', 'p.id')
                    ->where('e.capability', 'teams')
                    ->where('e.is_enabled', true);
            })
            ->leftJoin('data_provider_payload_mappings as m', 'm.data_provider_http_endpoint_id', '=', 'e.id')
            ->whereNotNull('e.id')
            ->orderByRaw('COALESCE(rc.priority, 9999)')
            ->orderByRaw("CASE e.operation WHEN 'by_competition' THEN 0 WHEN 'by_season' THEN 1 WHEN 'list' THEN 2 ELSE 3 END")
            ->orderBy('p.name')
            ->get([
                'p.id',
                'p.code',
                'p.name',
                'rc.is_enabled as runtime_enabled',
                'p.base_url as provider_base_url',
                'rc.base_url as runtime_base_url',
                'rc.timeout',
                'rc.connect_timeout',
                'rc.retry_times',
                'rc.retry_sleep_ms',
                'e.id as http_endpoint_id',
                'e.method',
                'e.endpoint',
                'e.query_params',
                'e.body_template',
                'e.items_path',
                'm.field_mappings',
            ])
            ->unique('id')
            ->values()
            ->all();
    }

    private function providerFromRow(object $provider): ?TeamDataProvider
    {
        if ($provider->http_endpoint_id !== null && $provider->field_mappings !== null) {
            return new GenericHttpTeamProvider(
                provider: [
                    'id' => (int) $provider->id,
                    'code' => (string) $provider->code,
                    'name' => (string) $provider->name,
                    'base_url' => (string) ($provider->runtime_base_url ?? $provider->provider_base_url),
                    'timeout' => (int) ($provider->timeout ?? 30),
                    'connect_timeout' => (int) ($provider->connect_timeout ?? 10),
                    'retry_times' => (int) ($provider->retry_times ?? 0),
                    'retry_sleep_ms' => (int) ($provider->retry_sleep_ms ?? 0),
                ],
                endpoint: [
                    'method' => (string) $provider->method,
                    'endpoint' => (string) $provider->endpoint,
                    'query_params' => json_decode((string) ($provider->query_params ?? '[]'), true) ?: [],
                    'body_template' => json_decode((string) ($provider->body_template ?? '[]'), true) ?: [],
                    'items_path' => $provider->items_path ? (string) $provider->items_path : null,
                ],
                fieldMappings: json_decode((string) $provider->field_mappings, true) ?: [],
                headers: $this->authentication->headers((int) $provider->id),
                queryParameters: $this->authentication->queryParameters((int) $provider->id),
                mapper: $this->mapper,
            );
        }

        return null;
    }
}
