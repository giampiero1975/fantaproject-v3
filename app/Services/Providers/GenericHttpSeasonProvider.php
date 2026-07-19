<?php

namespace App\Services\Providers;

use App\Data\Providers\ProviderSeasonResult;
use App\Data\Providers\SeasonDataRequest;
use App\Data\Seasons\CanonicalSeasonData;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class GenericHttpSeasonProvider
{
    /**
     * @param  array<string, mixed>  $provider
     * @param  array<string, mixed>  $endpoint
     * @param  array<string, string>  $fieldMappings
     * @param  array<string, string>  $headers
     * @param  array<string, string>  $queryParameters
     */
    public function __construct(
        private readonly array $provider,
        private readonly array $endpoint,
        private readonly array $fieldMappings,
        private readonly array $headers,
        private readonly array $queryParameters,
        private readonly HttpProviderPayloadMapper $mapper,
    ) {}

    public function key(): string
    {
        return (string) $this->provider['code'];
    }

    public function fetchSeason(SeasonDataRequest $request): ProviderSeasonResult
    {
        $reference = $request->referenceFor($this->key());
        if ($reference === null || trim((string) $reference) === '') {
            return ProviderSeasonResult::unavailable($this->key(), 'missing_provider_reference');
        }

        $variables = [
            'provider_competition_code' => (string) $reference,
            'provider_competition_id' => (string) $reference,
            'provider_league_id' => (string) $reference,
            'league_id' => (string) $reference,
            'season_year' => (string) $request->seasonYear,
            'season_label' => $this->seasonLabel($request->seasonYear),
        ];

        try {
            $endpoint = $this->render((string) $this->endpoint['endpoint'], $variables);
            if ($this->hasUnresolvedPlaceholders($endpoint)) {
                return ProviderSeasonResult::unavailable($this->key(), 'unresolved_endpoint_template_variables');
            }

            $httpRequest = $this->request();
            $queryParameters = $this->renderArray($this->endpoint['query_params'] ?? [], $variables);
            if ($this->hasUnresolvedPlaceholders($queryParameters)) {
                return ProviderSeasonResult::unavailable($this->key(), 'unresolved_query_template_variables');
            }

            $url = $this->url($endpoint);
            $response = strtoupper((string) ($this->endpoint['method'] ?? 'GET')) === 'POST'
                ? $httpRequest->post($url, $this->renderArray($this->endpoint['body_template'] ?? [], $variables))
                : $httpRequest->get($url, array_merge(
                    $this->queryParameters,
                    $queryParameters,
                ));

            $payload = $response->throw()->json();

            if (! is_array($payload)) {
                return ProviderSeasonResult::unavailable($this->key(), 'invalid_json_payload');
            }

            $items = $this->mapper->extractItems($payload, $this->endpoint['items_path'] ?? null);
            if ($items === []) {
                return ProviderSeasonResult::unavailable($this->key(), 'empty_payload');
            }

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $mapped = $this->mapper->mapFields($item, $this->fieldMappings);
                $season = $this->canonicalSeason($mapped);

                if ($season->externalId !== null || $season->startDate !== null || $season->endDate !== null || $season->metadata !== []) {
                    return ProviderSeasonResult::available($this->key(), $season);
                }
            }

            return ProviderSeasonResult::unavailable($this->key(), 'mapped_payload_missing_season_fields');
        } catch (RequestException $e) {
            $status = $e->response->status();
            if (in_array($status, [401, 403], true)) {
                return ProviderSeasonResult::unavailable($this->key(), 'unavailable_for_current_credentials_or_plan');
            }

            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException("HTTP season provider {$this->key()} failed: {$e->getMessage()}", previous: $e);
        }
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::acceptJson()
            ->connectTimeout((int) ($this->provider['connect_timeout'] ?? 10))
            ->timeout((int) ($this->provider['timeout'] ?? 30))
            ->retry((int) ($this->provider['retry_times'] ?? 0), (int) ($this->provider['retry_sleep_ms'] ?? 0), throw: false);

        foreach ($this->headers as $header => $value) {
            $request = $request->withHeaders([$header => $value]);
        }

        return $request;
    }

    private function url(string $endpoint): string
    {
        $baseUrl = rtrim((string) $this->provider['base_url'], '/');
        $endpoint = ltrim($endpoint, '/');

        return "{$baseUrl}/{$endpoint}";
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $variables
     * @return array<string, mixed>
     */
    private function renderArray(array $values, array $variables): array
    {
        return collect($values)
            ->map(function (mixed $value) use ($variables): mixed {
                if (is_array($value)) {
                    return $this->renderArray($value, $variables);
                }

                return is_string($value) ? $this->render($value, $variables) : $value;
            })
            ->all();
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function render(string $value, array $variables): string
    {
        foreach ($variables as $key => $replacement) {
            $value = str_replace('{'.$key.'}', $replacement, $value);
        }

        return $value;
    }

    private function hasUnresolvedPlaceholders(mixed $value): bool
    {
        if (is_array($value)) {
            return collect($value)->contains(fn (mixed $item): bool => $this->hasUnresolvedPlaceholders($item));
        }

        return is_string($value) && preg_match('/\{[A-Za-z0-9_]+\}/', $value) === 1;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function canonicalSeason(array $item): CanonicalSeasonData
    {
        $externalId = $this->stringOrNull($item['season_id'] ?? $item['provider_season_id'] ?? $item['external_id'] ?? null);
        $startDate = $this->dateOrNull($item['start_date'] ?? $item['startDate'] ?? null);
        $endDate = $this->dateOrNull($item['end_date'] ?? $item['endDate'] ?? null);

        return new CanonicalSeasonData(
            provider: $this->key(),
            externalId: $externalId,
            startDate: $startDate,
            endDate: $endDate,
            metadata: $item,
        );
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function dateOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1
            ? substr($value, 0, 10)
            : null;
    }

    private function seasonLabel(int $seasonYear): string
    {
        return sprintf('%d-%d', $seasonYear, $seasonYear + 1);
    }
}
