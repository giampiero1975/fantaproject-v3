<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->integer('tier_globale')->nullable()->after('crest_url');
            $table->decimal('posizione_media_storica', 8, 4)->nullable()->after('tier_globale');
            $table->index('tier_globale', 'teams_tier_globale_idx');
        });

        Schema::table('league_season_teams', function (Blueprint $table): void {
            $table->integer('tier_stagionale')->nullable()->after('team_id');
            $table->integer('posizione_finale')->nullable()->after('tier_stagionale');
            $table->integer('punti')->nullable()->after('posizione_finale');
        });
    }

    public function down(): void
    {
        Schema::table('league_season_teams', function (Blueprint $table): void {
            $table->dropColumn(['tier_stagionale', 'posizione_finale', 'punti']);
        });

        Schema::table('teams', function (Blueprint $table): void {
            $table->dropIndex('teams_tier_globale_idx');
            $table->dropColumn(['tier_globale', 'posizione_media_storica']);
        });
    }
};
