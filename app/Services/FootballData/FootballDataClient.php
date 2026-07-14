<?php

namespace App\Services\FootballData;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class FootballDataClient
{
    public function teams(string $competitionCode, int $seasonYear): array
    {
        $payload = $this->request()
            ->get("/competitions/{$competitionCode}/teams", ['season' => $seasonYear])
            ->throw()
            ->json();

        if (! is_array($payload)) {
            throw new RuntimeException('football-data.org returned an invalid JSON payload.');
        }

        return $payload;
    }

    private function request(): PendingRequest
    {
        $token = (string) config('football_data.token');
        if ($token === '') {
            throw new RuntimeException('FOOTBALL_DATA_TOKEN is not configured.');
        }

        return Http::baseUrl((string) config('football_data.base_url'))
            ->acceptJson()
            ->withHeaders(['X-Auth-Token' => $token])
            ->connectTimeout((int) config('football_data.connect_timeout', 10))
            ->timeout((int) config('football_data.timeout', 30))
            ->retry(
                (int) config('football_data.retry_times', 3),
                (int) config('football_data.retry_sleep_ms', 500),
                throw: false,
            );
    }
}
