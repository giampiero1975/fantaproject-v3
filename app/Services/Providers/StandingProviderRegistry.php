<?php

namespace App\Services\Providers;

use App\Contracts\Providers\StandingDataProvider;
use Illuminate\Support\Facades\DB;

final class StandingProviderRegistry
{
    public function __construct(
        private readonly HttpProviderPayloadMapper $mapper,
        private readonly ProviderHttpAuthentication $authentication,
    ) {}

    /** @return list<StandingDataProvider> */
    public function all(): array
    {
        return collect($this->providerRows())
            ->map(fn (object $provider): ?StandingDataProvider => $this->providerFromRow($provider))
            ->filter()
            ->values()
            ->all();
    }

    /** @return list<object> */
    private function providerRows(): array
    {
        return DB::table('data_providers as p')
            ->leftJoin('data_provider_runtime_configs as rc', 'rc.data_provider_id', '=', 'p.id')
            ->leftJoin('data_provider_http_endpoints as e', function ($join): void {
                $join->on('e.data_provider_id', '=', 'p.id')
                    ->where('e.capability', 'standings')
                    ->where('e.is_enabled', true);
            })
            ->leftJoin('data_provider_payload_mappings as m', 'm.data_provider_http_endpoint_id', '=', 'e.id')
            ->whereNotNull('e.id')
            ->whereNotNull('m.field_mappings')
            ->where('m.validation_status', 'mapping_validated')
            ->where('p.active', true)
            ->where('rc.is_enabled', true)
            ->orderByRaw('COALESCE(rc.priority, 9999)')
            ->orderByRaw("CASE e.operation WHEN 'by_season' THEN 0 WHEN 'by_competition' THEN 1 ELSE 2 END")
            ->orderBy('p.name')
            ->get([
                'p.id',
                'p.code',
                'p.name',
                'p.base_url as provider_base_url',
                'rc.base_url as runtime_base_url',
                'rc.timeout',
                'rc.connect_timeout',
                'rc.retry_times',
                'rc.retry_sleep_ms',
                'e.id as http_endpoint_id',
                'e.operation',
                'e.method',
                'e.auth_mode',
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

    private function providerFromRow(object $provider): ?StandingDataProvider
    {
        if ($provider->http_endpoint_id === null || $provider->field_mappings === null) {
            return null;
        }

        return new GenericHttpStandingProvider(
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
                'id' => (int) $provider->http_endpoint_id,
                'capability' => 'standings',
                'operation' => (string) $provider->operation,
                'method' => (string) $provider->method,
                'auth_mode' => (string) ($provider->auth_mode ?? 'default'),
                'endpoint' => (string) $provider->endpoint,
                'query_params' => json_decode((string) ($provider->query_params ?? '[]'), true) ?: [],
                'body_template' => json_decode((string) ($provider->body_template ?? '[]'), true) ?: [],
                'items_path' => $provider->items_path ? (string) $provider->items_path : null,
            ],
            fieldMappings: json_decode((string) $provider->field_mappings, true) ?: [],
            headers: ($provider->auth_mode ?? 'default') === 'none' ? [] : $this->authentication->headers((int) $provider->id),
            queryParameters: ($provider->auth_mode ?? 'default') === 'none' ? [] : $this->authentication->queryParameters((int) $provider->id),
            mapper: $this->mapper,
        );
    }
}
