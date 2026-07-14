<?php

return [
    'base_url' => env('FOOTBALL_DATA_BASE_URL', 'https://api.football-data.org/v4'),
    'token' => env('FOOTBALL_DATA_TOKEN'),
    'connect_timeout' => (int) env('FOOTBALL_DATA_CONNECT_TIMEOUT', 10),
    'timeout' => (int) env('FOOTBALL_DATA_TIMEOUT', 30),
    'retry_times' => (int) env('FOOTBALL_DATA_RETRY_TIMES', 3),
    'retry_sleep_ms' => (int) env('FOOTBALL_DATA_RETRY_SLEEP_MS', 500),
];
