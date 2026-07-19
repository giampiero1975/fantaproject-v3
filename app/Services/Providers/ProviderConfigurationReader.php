<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\DB;

final class ProviderConfigurationReader
{
    /**
     * @return array<string, mixed>
     */
    public function values(int $providerId, ?string $environment = null): array
    {
        return DB::table('data_provider_configurations')
            ->where('data_provider_id', $providerId)
            ->where('environment', $environment)
            ->get(['key', 'value', 'value_type'])
            ->mapWithKeys(fn (object $row): array => [
                (string) $row->key => $this->parseValue($row->value, (string) $row->value_type),
            ])
            ->all();
    }

    private function parseValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value === 'true' || $value === '1',
            'integer' => (int) $value,
            'json' => json_decode((string) $value, true),
            default => (string) $value,
        };
    }
}
