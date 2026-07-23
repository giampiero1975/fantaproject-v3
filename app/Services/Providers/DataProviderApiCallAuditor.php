<?php

namespace App\Services\Providers;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DataProviderApiCallAuditor
{
    /** @param array<string,mixed> $provider @param array<string,mixed> $endpoint @param array<string,mixed> $query @param array<string,mixed> $body @param array<string,mixed> $context */
    public function record(
        array $provider,
        array $endpoint,
        string $method,
        string $url,
        array $query,
        array $body,
        ?Response $response,
        int $itemsCount,
        int $durationMs,
        array $context = [],
        ?string $errorCode = null,
        ?string $errorMessage = null,
    ): string {
        $uuid = (string) Str::uuid();
        $resolvedEndpoint = $this->resolvedEndpoint($url);
        $sanitizedQuery = $this->sanitizeQuery($query);
        $providerHeaders = $response ? $this->providerResponseHeaders($response) : [];

        DB::table('data_provider_api_call_audits')->insert([
            'uuid' => $uuid,
            'data_provider_id' => (int) $provider['id'],
            'data_provider_http_endpoint_id' => isset($endpoint['id']) && (int) $endpoint['id'] > 0 ? (int) $endpoint['id'] : null,
            'provider_code' => (string) $provider['code'],
            'capability' => (string) ($endpoint['capability'] ?? ''),
            'operation' => (string) ($endpoint['operation'] ?? ''),
            'method' => strtoupper($method),
            'endpoint_template' => isset($endpoint['endpoint']) ? (string) $endpoint['endpoint'] : null,
            'resolved_endpoint' => $resolvedEndpoint,
            'resolved_query' => $sanitizedQuery === [] ? null : json_encode($sanitizedQuery),
            'status_code' => $response?->status(),
            'duration_ms' => $durationMs,
            'items_count' => $itemsCount,
            'response_fingerprint' => $response ? hash('sha256', $response->body()) : null,
            'provider_headers' => $providerHeaders === [] ? null : json_encode($providerHeaders),
            'error_code' => $errorCode ?? $this->statusErrorCode($response),
            'error_message' => $errorMessage ? Str::limit($errorMessage, 1000, '') : null,
            'sync_type' => $context['sync_type'] ?? null,
            'sync_target_type' => $context['sync_target_type'] ?? null,
            'sync_target_id' => isset($context['sync_target_id']) ? (int) $context['sync_target_id'] : null,
            'called_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $uuid;
    }

    /** @return array<string,string> */
    private function providerResponseHeaders(Response $response): array
    {
        $headers = [];

        foreach ([
            'content-type' => ['content-type'],
            'x-api-version' => ['x-api-version'],
            'x-authenticated-client' => ['x-authenticated-client'],
            'x-requestcounter-reset' => ['x-requestcounter-reset'],
            'x-requests-available-minute' => ['x-requests-available-minute'],
            'x-requestsavailable' => ['x-requestsavailable', 'x-requests-available'],
            'x-cache-status' => ['x-cache-status'],
            'content-language' => ['content-language'],
            'content-encoding' => ['content-encoding'],
            'server' => ['server'],
            'date' => ['date'],
        ] as $metadataKey => $aliases) {
            $value = null;
            foreach ($aliases as $header) {
                $value = $response->header($header) ?? $this->headerValue($response, $header);
                if ($value !== null && trim((string) $value) !== '') {
                    break;
                }
            }

            if ($value !== null && trim((string) $value) !== '') {
                $headers[$metadataKey] = trim((string) $value);
            }
        }

        return $headers;
    }

    private function headerValue(Response $response, string $header): ?string
    {
        foreach ($response->headers() as $name => $values) {
            if (strtolower((string) $name) !== strtolower($header)) {
                continue;
            }

            $value = is_array($values) ? ($values[0] ?? null) : $values;

            return $value !== null ? (string) $value : null;
        }

        return null;
    }

    private function statusErrorCode(?Response $response): ?string
    {
        if ($response === null || $response->successful()) {
            return null;
        }

        return 'http_'.$response->status();
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    private function sanitizeQuery(array $query): array
    {
        return collect($query)
            ->reject(fn (mixed $value, string $key): bool => in_array(strtolower($key), ['token', 'api_key', 'auth_token', 'key', 'x-auth-token'], true))
            ->all();
    }

    private function resolvedEndpoint(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) ? $path : $url;
    }
}
