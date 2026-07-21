<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_season_team_standings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_season_team_id')
                ->constrained('league_season_teams')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->unsignedInteger('position')->nullable();
            $table->unsignedInteger('played_games')->nullable();
            $table->unsignedInteger('won')->nullable();
            $table->unsignedInteger('draw')->nullable();
            $table->unsignedInteger('lost')->nullable();
            $table->integer('points')->nullable();
            $table->integer('goals_for')->nullable();
            $table->integer('goals_against')->nullable();
            $table->integer('goal_difference')->nullable();
            $table->string('stage_name', 120)->nullable();
            $table->string('group_name', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['league_season_team_id', 'stage_name', 'group_name'], 'league_team_standing_unique');
            $table->index(['position', 'points']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_season_team_standings');
    }
};