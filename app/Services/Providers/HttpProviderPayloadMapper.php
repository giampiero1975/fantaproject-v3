<?php

namespace App\Services\Providers;

use Illuminate\Support\Arr;

final class HttpProviderPayloadMapper
{
    /**
     * @return list<mixed>
     */
    public function extractItems(mixed $payload, ?string $itemsPath): array
    {
        $items = filled($itemsPath)
            ? data_get($payload, (string) $itemsPath)
            : $payload;

        if (! is_array($items)) {
            return [];
        }

        return Arr::isAssoc($items) ? [$items] : array_values($items);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, string>  $fieldMappings
     * @return array<string, mixed>
     */
    public function mapFields(array $item, array $fieldMappings): array
    {
        return collect($fieldMappings)
            ->mapWithKeys(fn (string $sourcePath, string $targetField): array => [
                $targetField => $this->mappedValue($item, $sourcePath),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function mappedValue(array $item, string $sourcePath): mixed
    {
        if (preg_match('/^pluck\(([^,]+),\s*([^)]+)\)$/', trim($sourcePath), $matches) === 1) {
            $items = data_get($item, trim($matches[1]));
            $valuePath = trim($matches[2]);

            if (! is_array($items)) {
                return [];
            }

            $items = Arr::isAssoc($items) ? [$items] : $items;

            return collect($items)
                ->map(fn (mixed $nestedItem): mixed => is_array($nestedItem)
                    ? data_get($nestedItem, $valuePath)
                    : null)
                ->filter(fn (mixed $value): bool => $value !== null)
                ->values()
                ->all();
        }

        if (preg_match('/^map\(([^,]+),\s*(.+)\)$/', trim($sourcePath), $matches) === 1) {
            $items = data_get($item, trim($matches[1]));
            $nestedMappings = $this->parseInlineMappings(trim($matches[2]));

            if (! is_array($items) || $nestedMappings === []) {
                return [];
            }

            $items = Arr::isAssoc($items) ? [$items] : $items;

            return collect($items)
                ->filter(fn (mixed $nestedItem): bool => is_array($nestedItem))
                ->map(fn (array $nestedItem): array => collect($nestedMappings)
                    ->mapWithKeys(fn (string $nestedSourcePath, string $nestedTargetField): array => [
                        $nestedTargetField => data_get($nestedItem, $nestedSourcePath),
                    ])
                    ->all())
                ->values()
                ->all();
        }

        return data_get($item, $sourcePath);
    }

    /**
     * @return array<string, string>
     */
    private function parseInlineMappings(string $value): array
    {
        return collect(explode(',', $value))
            ->mapWithKeys(function (string $pair): array {
                [$field, $path] = array_pad(explode('=', $pair, 2), 2, null);
                $field = trim((string) $field);
                $path = trim((string) $path);

                return $field !== '' && $path !== ''
                    ? [$field => $path]
                    : [];
            })
            ->all();
    }
}
