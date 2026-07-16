<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_provider_adapter_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->string('adapter_class', 255)->nullable();
            $table->string('config_key', 100)->nullable();
            $table->string('credential_key', 100)->nullable();
            $table->json('capabilities');
            $table->boolean('is_installed')->default(true);
            $table->timestamps();
        });

        DB::table('data_provider_adapter_definitions')->insert([
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
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('data_provider_adapter_definitions');
    }
};
