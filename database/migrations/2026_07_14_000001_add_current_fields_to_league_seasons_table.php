<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_seasons', function (Blueprint $table) {
            $table->boolean('is_current')->default(false)->after('season_id');
            $table->string('status', 30)->default('active')->after('is_current');
            $table->index(['league_id', 'is_current'], 'league_seasons_current_idx');
        });
    }

    public function down(): void
    {
        Schema::table('league_seasons', function (Blueprint $table) {
            $table->dropIndex('league_seasons_current_idx');
            $table->dropColumn(['is_current', 'status']);
        });
    }
};
