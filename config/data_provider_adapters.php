<?php

return [
    'football_data' => [
        'name' => 'football-data.org',
        'credential_key' => 'token',
        'capabilities' => ['competitions', 'seasons', 'teams'],
    ],

    'api_football' => [
        'name' => 'API-Football',
        'credential_key' => 'api_key',
        'capabilities' => ['competitions', 'seasons', 'teams'],
    ],

    'thesportsdb' => [
        'name' => 'TheSportsDB',
        'credential_key' => null,
        'capabilities' => ['competitions', 'seasons', 'teams'],
    ],
];
