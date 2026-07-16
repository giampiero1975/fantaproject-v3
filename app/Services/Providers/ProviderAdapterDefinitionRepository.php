<?php

namespace App\Services\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ProviderAdapterDefinitionRepository
{
    /**
     * @return Collection<string, array<string, mixed>>
     */
    public function installed(): Collection
    {
        return DB::table('data_provider_adapter_definitions')
            ->where('is_installed', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (object $adapter): array => [
                (string) $adapter->code => $this->format($adapter),
            ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findInstalled(string $code): ?array
    {
        $adapter = DB::table('data_provider_adapter_definitions')
            ->where('code', $code)
            ->where('is_installed', true)
            ->first();

        return $adapter ? $this->format($adapter) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function format(object $adapter): array
    {
        $capabilities = json_decode((string) $adapter->capabilities, true);

        return [
            'code' => (string) $adapter->code,
            'name' => (string) $adapter->name,
            'adapter_class' => $adapter->adapter_class ? (string) $adapter->adapter_class : null,
            'config_key' => $adapter->config_key ? (string) $adapter->config_key : null,
            'credential_key' => $adapter->credential_key ? (string) $adapter->credential_key : null,
            'capabilities' => is_array($capabilities) ? array_values($capabilities) : [],
        ];
    }
}
