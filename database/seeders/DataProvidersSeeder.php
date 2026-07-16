<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DataProvidersSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('data_providers')->upsert([
            [
                'code' => 'football_data',
                'name' => 'football-data.org',
                'base_url' => 'https://api.football-data.org/v4',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'api_football',
                'name' => 'API-Football',
                'base_url' => 'https://v3.football.api-sports.io',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['code'], ['name', 'base_url', 'active', 'updated_at']);

        if (! Schema::hasTable('data_provider_adapter_definitions')) {
            return;
        }

        DB::table('data_provider_adapter_definitions')->upsert([
            [
                'code' => 'football_data',
                'name' => 'football-data.org',
                'adapter_class' => 'App\\Services\\Providers\\FootballDataTeamProvider',
                'config_key' => 'football_data',
                'credential_key' => 'token',
                'capabilities' => json_encode(['competitions', 'seasons', 'teams']),
                'is_installed' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'api_football',
                'name' => 'API-Football',
                'adapter_class' => 'App\\Services\\Providers\\ApiFootballTeamProvider',
                'config_key' => 'api_football',
                'credential_key' => 'api_key',
                'capabilities' => json_encode(['competitions', 'seasons', 'teams']),
                'is_installed' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['code'], ['name', 'adapter_class', 'config_key', 'credential_key', 'capabilities', 'is_installed', 'updated_at']);
    }
}
