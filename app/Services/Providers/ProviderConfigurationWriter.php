<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\DB;

final class ProviderConfigurationWriter
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function writeMany(int $providerId, array $values, ?string $environment = null): void
    {
        foreach ($values as $key => $value) {
            $this->write($providerId, (string) $key, $value, $environment);
        }
    }

    private function write(int $providerId, string $key, mixed $value, ?string $environment): void
    {
        DB::table('data_provider_configurations')->updateOrInsert(
            [
                'data_provider_id' => $providerId,
                'key' => $key,
                'environment' => $environment,
            ],
            [
                'value' => $this->serializeValue($value),
                'value_type' => $this->type($value),
                'is_secret' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function serializeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function type(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_array($value) => 'json',
            default => 'string',
        };
    }
}
