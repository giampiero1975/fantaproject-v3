<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_season_teams', function (Blueprint $table): void {
            if (! Schema::hasColumn('league_season_teams', 'tier_score')) {
                $table->decimal('tier_score', 8, 4)->nullable()->after('tier_stagionale');
            }
        });
    }

    public function down(): void
    {
        Schema::table('league_season_teams', function (Blueprint $table): void {
            if (Schema::hasColumn('league_season_teams', 'tier_score')) {
                $table->dropColumn('tier_score');
            }
        });
    }
};
