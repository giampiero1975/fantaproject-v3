<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DataProvidersSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('data_providers')->upsert([
            [
                'code' => 'api_football',
                'name' => 'API-Football',
                'base_url' => 'https://v3.football.api-sports.io',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['code'], ['name', 'base_url', 'active', 'updated_at']);
    }
}
