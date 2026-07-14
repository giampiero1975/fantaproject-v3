<?php

namespace App\Services\ApiFootball;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ApiFootballClient
{
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
        $items = $this->league($leagueId);
        $seasons = data_get($items, '0.seasons', []);

        if (! is_array($seasons)) {
            throw new RuntimeException('API-Football seasons payload is missing or invalid.');
        }

        foreach ($seasons as $season) {
            if (($season['current'] ?? false) === true && isset($season['year'])) {
                return (int) $season['year'];
            }
        }

        throw new RuntimeException('API-Football current season is not available.');
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
        $key = (string) config('api_football.key');
        if ($key === '') {
            throw new RuntimeException('API_FOOTBALL_KEY is not configured.');
        }

        return Http::baseUrl((string) config('api_football.base_url'))
            ->acceptJson()
            ->withHeaders(['x-apisports-key' => $key])
            ->connectTimeout((int) config('api_football.connect_timeout', 10))
            ->timeout((int) config('api_football.timeout', 30))
            ->retry(
                (int) config('api_football.retry_times', 3),
                (int) config('api_football.retry_sleep_ms', 500),
                throw: false,
            );
    }
}
