<?php

namespace App\Services\ApiFootball;

use App\Services\Providers\ProviderConfigurationRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ApiFootballClient
{
    public function __construct(
        private readonly ProviderConfigurationRepository $configurations,
    ) {}

    public function leagues(): array
    {
        return $this->response('/leagues');
    }

    public function league(int $leagueId): array
    {
        return $this->response('/leagues', ['id' => $leagueId]);
    }

    public function currentSeasonYear(int $leagueId): int
    {
        return $this->currentSeasonInfo($leagueId)['year'];
    }

    /** @return array{year:int,start_date:?string,end_date:?string} */
    public function currentSeasonInfo(int $leagueId): array
    {
        $seasons = $this->seasonList($leagueId);

        foreach ($seasons as $season) {
            if (($season['current'] ?? false) === true && isset($season['year'])) {
                return [
                    'year' => (int) $season['year'],
                    'start_date' => isset($season['start']) ? (string) $season['start'] : null,
                    'end_date' => isset($season['end']) ? (string) $season['end'] : null,
                ];
            }
        }

        throw new RuntimeException('API-Football current season is not available.');
    }

    /** @return array{start_date:?string,end_date:?string} */
    public function seasonDates(int $leagueId, int $seasonYear): array
    {
        foreach ($this->seasonList($leagueId) as $season) {
            if ((int) ($season['year'] ?? 0) === $seasonYear) {
                return [
                    'start_date' => isset($season['start']) ? (string) $season['start'] : null,
                    'end_date' => isset($season['end']) ? (string) $season['end'] : null,
                ];
            }
        }

        return ['start_date' => null, 'end_date' => null];
    }

    /** @return list<array<string,mixed>> */
    private function seasonList(int $leagueId): array
    {
        $items = $this->league($leagueId);
        $seasons = data_get($items, '0.seasons', []);

        if (! is_array($seasons)) {
            throw new RuntimeException('API-Football seasons payload is missing or invalid.');
        }

        return array_values($seasons);
    }

    public function teams(int $leagueId, int $seasonYear): array
    {
        return $this->payload('/teams', [
            'league' => $leagueId,
            'season' => $seasonYear,
        ]);
    }

    private function response(string $endpoint, array $query = []): array
    {
        $payload = $this->payload($endpoint, $query);
        $response = $payload['response'] ?? null;

        if (! is_array($response)) {
            throw new RuntimeException('API-Football response field is missing or invalid.');
        }

        return $response;
    }

    private function payload(string $endpoint, array $query = []): array
    {
        $payload = $this->request()->get($endpoint, $query)->throw()->json();

        if (! is_array($payload)) {
            throw new RuntimeException('API-Football returned an invalid JSON payload.');
        }

        $errors = $payload['errors'] ?? [];
        if (is_array($errors) && $errors !== []) {
            throw new RuntimeException('API-Football error: '.json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        return $payload;
    }

    private function request(): PendingRequest
    {
        $config = $this->configurations->get('api_football');
        if (! $config->enabled) {
            throw new RuntimeException('Provider api_football is disabled.');
        }

        $key = $config->credential('api_key');
        if ($key === '') {
            throw new RuntimeException('Credential api_key for api_football is not configured.');
        }

        return Http::baseUrl($config->baseUrl)
            ->acceptJson()
            ->withHeaders(['x-apisports-key' => $key])
            ->connectTimeout($config->connectTimeout)
            ->timeout($config->timeout)
            ->retry($config->retryTimes, $config->retrySleepMs, throw: false);
    }
}