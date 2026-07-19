<?php

namespace App\Services\Providers;

use App\Contracts\Providers\TeamDataProvider;
use App\Data\Providers\ProviderTeamResult;
use App\Data\Providers\TeamDataRequest;
use App\Data\Seasons\CanonicalTeamData;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class GenericHttpTeamProvider implements TeamDataProvider
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

    public function fetchTeams(TeamDataRequest $request): ProviderTeamResult
    {
        $reference = $request->referenceFor($this->key());
        if ($reference === null || trim((string) $reference) === '') {
            return ProviderTeamResult::unavailable($this->key(), 'missing_provider_reference');
        }

        $variables = [
            'provider_competition_code' => (string) $reference,
            'provider_competition_id' => (string) $reference,
            'provider_league_id' => (string) $reference,
            'league_id' => (string) $reference,
            'season_year' => (string) $request->seasonYear,
        ];

        try {
            $url = $this->url($this->render((string) $this->endpoint['endpoint'], $variables));
            $request = $this->request();
            $response = strtoupper((string) ($this->endpoint['method'] ?? 'GET')) === 'POST'
                ? $request->post($url, $this->renderArray($this->endpoint['body_template'] ?? [], $variables))
                : $request->get($url, array_merge(
                    $this->queryParameters,
                    $this->renderArray($this->endpoint['query_params'] ?? [], $variables),
                ));

            $payload = $response->throw()->json();

            if (! is_array($payload)) {
                return ProviderTeamResult::unavailable($this->key(), 'invalid_json_payload');
            }

            $items = $this->mapper->extractItems($payload, $this->endpoint['items_path'] ?? null);
            if ($items === []) {
                return ProviderTeamResult::unavailable($this->key(), 'empty_payload');
            }

            $teams = collect($items)
                ->filter(fn (mixed $item): bool => is_array($item))
                ->map(fn (array $item): array => $this->mapper->mapFields($item, $this->fieldMappings))
                ->map(fn (array $item): CanonicalTeamData => $this->canonicalTeam($item))
                ->filter(fn (CanonicalTeamData $team): bool => $team->externalId !== '' && $team->name !== '')
                ->values()
                ->all();

            return $teams !== []
                ? ProviderTeamResult::available($this->key(), $teams)
                : ProviderTeamResult::unavailable($this->key(), 'mapped_payload_missing_required_team_fields');
        } catch (RequestException $e) {
            $status = $e->response->status();
            if (in_array($status, [401, 403], true)) {
                return ProviderTeamResult::unavailable($this->key(), 'unavailable_for_current_credentials_or_plan');
            }

            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException("HTTP provider {$this->key()} failed: {$e->getMessage()}", previous: $e);
        }
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::acceptJson()
            ->connectTimeout((int) ($this->provider['connect_timeout'] ?? 10))
            ->timeout((int) ($this->provider['timeout'] ?? 30))
            ->retry((int) ($this->provider['retry_times'] ?? 0), (int) ($this->provider['retry_sleep_ms'] ?? 0), throw: false);

        foreach ($this->headers() as $header => $value) {
            $request = $request->withHeaders([$header => $value]);
        }

        return $request;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return $this->headers;
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

    /**
     * @param  array<string, mixed>  $item
     */
    private function canonicalTeam(array $item): CanonicalTeamData
    {
        return new CanonicalTeamData(
            provider: $this->key(),
            externalId: (string) ($item['provider_team_id'] ?? $item['team_id'] ?? $item['external_id'] ?? $item['id'] ?? ''),
            name: trim((string) ($item['team_name'] ?? $item['name'] ?? '')),
            shortName: ($short = trim((string) ($item['short_name'] ?? $item['shortName'] ?? ''))) !== '' ? $short : null,
            code: ($code = trim((string) ($item['team_code'] ?? $item['code'] ?? ''))) !== '' ? $code : null,
            country: ($country = trim((string) ($item['country_name'] ?? $item['country'] ?? ''))) !== '' ? $country : null,
            crestUrl: ($crest = trim((string) ($item['crest_url'] ?? $item['logo_url'] ?? $item['logo'] ?? ''))) !== '' ? $crest : null,
            metadata: $item,
        );
    }
}
