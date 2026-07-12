<?php

return [
    'base_url' => env('API_FOOTBALL_BASE_URL', 'https://v3.football.api-sports.io'),
    'key' => env('API_FOOTBALL_KEY'),
    'timeout' => (int) env('API_FOOTBALL_TIMEOUT', 30),
    'connect_timeout' => (int) env('API_FOOTBALL_CONNECT_TIMEOUT', 10),
    'retry_times' => (int) env('API_FOOTBALL_RETRY_TIMES', 3),
    'retry_sleep_ms' => (int) env('API_FOOTBALL_RETRY_SLEEP_MS', 500),
];
