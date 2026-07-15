<?php

namespace App\Services\Providers;

use App\Data\Providers\ProviderRuntimeConfiguration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ProviderConfigurationRepository
{
    public function get(string $code): ProviderRuntimeConfiguration
    {
        $row = DB::table('data_providers as p')
            ->join('data_provider_runtime_configs as c', 'c.data_provider_id', '=', 'p.id')
            ->where('p.code', $code)
            ->select([
                'p.id as provider_id',
                'p.code',
                'p.name',
                'c.is_enabled',
                'c.priority',
                'c.role',
                'c.base_url',
                'c.timeout',
                'c.connect_timeout',
                'c.retry_times',
                'c.retry_sleep_ms',
                'c.plan',
                'c.metadata',
            ])
            ->first();

        if (! $row) {
            throw new RuntimeException("Runtime configuration for provider {$code} was not found.");
        }

        $environment = app()->environment();
        $credentials = DB::table('data_provider_credentials')
            ->where('data_provider_id', $row->provider_id)
            ->where('environment', $environment)
            ->where('is_active', true)
            ->pluck('encrypted_value', 'credential_key')
            ->map(static fn (string $value): string => Crypt::decryptString($value))
            ->all();

        $metadata = json_decode((string) ($row->metadata ?? '{}'), true);

        return new ProviderRuntimeConfiguration(
            providerId: (int) $row->provider_id,
            code: (string) $row->code,
            name: (string) $row->name,
            enabled: (bool) $row->is_enabled,
            priority: (int) $row->priority,
            role: (string) $row->role,
            baseUrl: (string) $row->base_url,
            timeout: (int) $row->timeout,
            connectTimeout: (int) $row->connect_timeout,
            retryTimes: (int) $row->retry_times,
            retrySleepMs: (int) $row->retry_sleep_ms,
            plan: $row->plan !== null ? (string) $row->plan : null,
            metadata: is_array($metadata) ? $metadata : [],
            credentials: $credentials,
        );
    }

    /** @return list<ProviderRuntimeConfiguration> */
    public function enabled(): array
    {
        $codes = DB::table('data_providers as p')
            ->join('data_provider_runtime_configs as c', 'c.data_provider_id', '=', 'p.id')
            ->where('c.is_enabled', true)
            ->orderBy('c.priority')
            ->pluck('p.code');

        return $codes->map(fn (string $code): ProviderRuntimeConfiguration => $this->get($code))->all();
    }
}