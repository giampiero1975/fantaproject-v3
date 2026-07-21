<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id')
                ->nullable()
                ->constrained('countries')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('name', 180);
            $table->string('short_name', 120)->nullable();
            $table->string('code', 30)->nullable();
            $table->string('crest_url', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['country_id', 'name'], 'teams_country_name_unique');
            $table->index('name');
        });

        Schema::create('league_season_teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_season_id')
                ->constrained('league_seasons')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('team_id')
                ->constrained('teams')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['league_season_id', 'team_id'], 'league_season_team_unique');
            $table->index(['league_season_id', 'is_active']);
        });

        Schema::create('team_provider_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_season_team_id')
                ->constrained('league_season_teams')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('data_provider_id')
                ->constrained('data_providers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('external_id', 120);
            $table->string('external_name', 180);
            $table->string('external_code', 50)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['league_season_team_id', 'data_provider_id'], 'team_provider_mapping_unique');
            $table->index(['data_provider_id', 'external_id'], 'team_provider_external_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_provider_mappings');
        Schema::dropIfExists('league_season_teams');
        Schema::dropIfExists('teams');
    }
};
