<?php

namespace App\Services\FootballData;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class FootballDataClient
{
    public function competition(string $competitionCode): array
    {
        $payload = $this->request()->get("/competitions/{$competitionCode}")->throw()->json();

        if (! is_array($payload)) {
            throw new RuntimeException('football-data.org returned an invalid competition payload.');
        }

        return $payload;
    }

    public function currentSeasonYear(string $competitionCode): int
    {
        $info = $this->currentSeasonInfo($competitionCode);

        return $info['year'];
    }

    /** @return array{year:int,start_date:?string,end_date:?string} */
    public function currentSeasonInfo(string $competitionCode): array
    {
        $payload = $this->competition($competitionCode);
        $startDate = (string) data_get($payload, 'currentSeason.startDate', '');

        if (! preg_match('/^(\d{4})-/', $startDate, $matches)) {
            throw new RuntimeException('football-data.org current season is missing or invalid.');
        }

        return [
            'year' => (int) $matches[1],
            'start_date' => $startDate !== '' ? $startDate : null,
            'end_date' => ($end = (string) data_get($payload, 'currentSeason.endDate', '')) !== '' ? $end : null,
        ];
    }

    /** @return array{start_date:?string,end_date:?string} */
    public function seasonDates(string $competitionCode, int $seasonYear): array
    {
        $payload = $this->teams($competitionCode, $seasonYear);

        return [
            'start_date' => ($start = (string) data_get($payload, 'season.startDate', '')) !== '' ? $start : null,
            'end_date' => ($end = (string) data_get($payload, 'season.endDate', '')) !== '' ? $end : null,
        ];
    }

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
