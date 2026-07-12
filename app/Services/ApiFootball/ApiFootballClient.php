<?php

namespace App\Services\ApiFootball;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ApiFootballClient
{
    public function leagues(): array
    {
        $payload = $this->request()->get('/leagues')->throw()->json();

        if (! is_array($payload)) {
            throw new RuntimeException('API-Football returned an invalid JSON payload.');
        }

        $errors = $payload['errors'] ?? [];
        if (is_array($errors) && $errors !== []) {
            throw new RuntimeException('API-Football error: '.json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $response = $payload['response'] ?? null;
        if (! is_array($response)) {
            throw new RuntimeException('API-Football response field is missing or invalid.');
        }

        return $response;
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
                throw: false
            );
    }
}
