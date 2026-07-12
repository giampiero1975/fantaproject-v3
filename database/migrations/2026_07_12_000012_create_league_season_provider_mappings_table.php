<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_season_provider_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_season_id')->constrained('league_seasons')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('data_provider_id')->constrained('data_providers')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('external_id', 100)->nullable();
            $table->unsignedSmallInteger('external_year')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['league_season_id', 'data_provider_id'], 'league_season_provider_unique');
            $table->index(['data_provider_id', 'external_id'], 'league_season_provider_external_id_idx');
            $table->index(['data_provider_id', 'external_year'], 'league_season_provider_external_year_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_season_provider_mappings');
    }
};
