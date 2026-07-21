<?php

namespace App\Services\Providers;

use App\Contracts\Providers\StandingDataProvider;
use App\Data\Providers\ProviderStandingResult;
use App\Data\Providers\StandingDataRequest;
use App\Data\Seasons\CanonicalStandingData;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class GenericHttpStandingProvider implements StandingDataProvider
{
    /**
     * @param array<string,mixed> $provider
     * @param array<string,mixed> $endpoint
     * @param array<string,string> $fieldMappings
     * @param array<string,string> $headers
     * @param array<string,string> $queryParameters
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

    public function fetchStandings(StandingDataRequest $request): ProviderStandingResult
    {
        $reference = $request->referenceFor($this->key());
        if ($reference === null || trim((string) $reference) === '') {
            return ProviderStandingResult::unavailable($this->key(), 'missing_provider_reference');
        }

        $variables = [
            'provider_competition_code' => (string) $reference,
            'provider_competition_id' => (string) $reference,
            'provider_league_id' => (string) $reference,
            'league_id' => (string) $reference,
            'season_year' => (string) $request->seasonYear,
            'season_label' => $request->seasonLabel,
        ];

        try {
            $url = $this->url($this->render((string) $this->endpoint['endpoint'], $variables));
            $httpRequest = $this->request();
            $method = strtoupper((string) ($this->endpoint['method'] ?? 'GET'));
            $startedAt = microtime(true);
            $query = array_merge(
                $this->queryParameters,
                $this->renderArray($this->endpoint['query_params'] ?? [], $variables),
            );
            $body = $this->renderArray($this->endpoint['body_template'] ?? [], $variables);
            $response = $method === 'POST'
                ? $httpRequest->post($url, $body)
                : $httpRequest->get($url, $query);

            $payload = $response->throw()->json();

            if (! is_array($payload)) {
                return ProviderStandingResult::unavailable($this->key(), 'invalid_json_payload');
            }

            $items = $this->mapper->extractItems($payload, $this->endpoint['items_path'] ?? null);
            app(DataProviderApiCallAuditor::class)->record(
                provider: $this->provider,
                endpoint: $this->endpoint + ['capability' => 'standings'],
                method: $method,
                url: $url,
                query: $query,
                body: $method === 'POST' ? $body : [],
                response: $response,
                itemsCount: count($items),
                durationMs: (int) round((microtime(true) - $startedAt) * 1000),
                context: [
                    'sync_type' => 'standings_sync',
                    'sync_target_type' => 'season_year',
                    'sync_target_id' => $request->seasonYear,
                ],
            );
            if ($items === []) {
                return ProviderStandingResult::unavailable($this->key(), 'empty_payload');
            }

            $standings = collect($items)
                ->filter(fn (mixed $item): bool => is_array($item))
                ->map(fn (array $item): array => $this->mapper->mapFields($item, $this->fieldMappings))
                ->map(fn (array $item): CanonicalStandingData => $this->canonicalStanding($item))
                ->filter(fn (CanonicalStandingData $standing): bool => $standing->providerTeamId !== '' || $standing->teamName !== '')
                ->values()
                ->all();

            return $standings !== []
                ? ProviderStandingResult::available($this->key(), $standings)
                : ProviderStandingResult::unavailable($this->key(), 'mapped_payload_missing_required_standing_fields');
        } catch (RequestException $e) {
            $status = $e->response->status();
            if (in_array($status, [401, 403], true)) {
                return ProviderStandingResult::unavailable($this->key(), 'unavailable_for_current_credentials_or_plan');
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

        foreach ($this->headers as $header => $value) {
            $request = $request->withHeaders([$header => $value]);
        }

        return $request;
    }

    private function url(string $endpoint): string
    {
        return rtrim((string) $this->provider['base_url'], '/').'/'.ltrim($endpoint, '/');
    }

    /** @param array<string,mixed> $values @param array<string,string> $variables @return array<string,mixed> */
    private function renderArray(array $values, array $variables): array
    {
        return collect($values)
            ->map(fn (mixed $value): mixed => is_array($value)
                ? $this->renderArray($value, $variables)
                : (is_string($value) ? $this->render($value, $variables) : $value))
            ->all();
    }

    /** @param array<string,string> $variables */
    private function render(string $value, array $variables): string
    {
        foreach ($variables as $key => $replacement) {
            $value = str_replace('{'.$key.'}', $replacement, $value);
        }

        return $value;
    }

    /** @param array<string,mixed> $item */
    private function canonicalStanding(array $item): CanonicalStandingData
    {
        return new CanonicalStandingData(
            provider: $this->key(),
            providerTeamId: (string) ($item['provider_team_id'] ?? $item['team_id'] ?? $item['external_id'] ?? ''),
            teamName: trim((string) ($item['team_name'] ?? $item['name'] ?? '')),
            teamCode: ($code = trim((string) ($item['team_code'] ?? $item['code'] ?? ''))) !== '' ? $code : null,
            position: $this->nullableInt($item['position'] ?? null),
            playedGames: $this->nullableInt($item['played_games'] ?? $item['playedGames'] ?? null),
            won: $this->nullableInt($item['won'] ?? $item['wins'] ?? null),
            draw: $this->nullableInt($item['draw'] ?? $item['draws'] ?? null),
            lost: $this->nullableInt($item['lost'] ?? $item['losses'] ?? null),
            points: $this->nullableInt($item['points'] ?? null),
            goalsFor: $this->nullableInt($item['goals_for'] ?? $item['goalsFor'] ?? null),
            goalsAgainst: $this->nullableInt($item['goals_against'] ?? $item['goalsAgainst'] ?? null),
            goalDifference: $this->nullableInt($item['goal_difference'] ?? $item['goalDifference'] ?? null),
            stageName: ($stage = trim((string) ($item['stage_name'] ?? ''))) !== '' ? $stage : null,
            groupName: ($group = trim((string) ($item['group_name'] ?? ''))) !== '' ? $group : null,
            metadata: $item,
        );
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
