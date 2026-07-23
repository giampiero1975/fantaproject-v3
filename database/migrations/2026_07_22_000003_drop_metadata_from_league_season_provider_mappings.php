<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('league_season_provider_mappings', 'metadata')) {
            Schema::table('league_season_provider_mappings', function (Blueprint $table): void {
                $table->dropColumn('metadata');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('league_season_provider_mappings', 'metadata')) {
            Schema::table('league_season_provider_mappings', function (Blueprint $table): void {
                $table->json('metadata')->nullable();
            });
        }
    }
};
