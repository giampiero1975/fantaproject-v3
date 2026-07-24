<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_team_tier_audit_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('seasons_count');
            $table->unsignedInteger('observations_count');
            $table->decimal('ranking_accuracy_pct', 6, 2);
            $table->decimal('position_mae', 6, 2);
            $table->decimal('exact_tier_pct', 6, 2);
            $table->decimal('within_one_tier_pct', 6, 2);
            $table->timestamps();
        });

        Schema::create('ai_team_tier_audit_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_team_tier_audit_run_id')
                ->constrained('ai_team_tier_audit_runs')
                ->cascadeOnDelete();
            $table->foreignId('league_season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('target_season_key');
            $table->unsignedSmallInteger('predicted_position');
            $table->unsignedSmallInteger('actual_position');
            $table->unsignedTinyInteger('predicted_tier');
            $table->unsignedTinyInteger('actual_tier')->nullable();
            $table->decimal('predicted_score', 10, 4);
            $table->decimal('actual_score', 10, 4)->nullable();
            $table->smallInteger('position_error');
            $table->unsignedSmallInteger('absolute_position_error');
            $table->unsignedTinyInteger('history_available');
            $table->unsignedTinyInteger('history_requested');
            $table->decimal('history_coverage', 7, 4);
            $table->decimal('latest_points_per_game', 9, 4)->nullable();
            $table->decimal('latest_goals_for_per_game', 9, 4)->nullable();
            $table->decimal('latest_goals_against_per_game', 9, 4)->nullable();
            $table->decimal('latest_goal_difference_per_game', 9, 4)->nullable();
            $table->decimal('latest_relative_strength', 7, 4)->nullable();
            $table->decimal('trend_slope', 9, 5)->nullable();
            $table->decimal('volatility', 9, 5)->nullable();
            $table->decimal('regression_gap', 9, 5)->nullable();
            $table->boolean('is_promoted')->default(false);
            $table->decimal('lower_league_relative_strength', 7, 4)->nullable();
            $table->decimal('actual_relative_strength', 7, 4);
            $table->timestamps();

            $table->unique(
                ['ai_team_tier_audit_run_id', 'league_season_id', 'team_id'],
                'team_tier_signal_audit_observation_unique'
            );
            $table->index(['league_season_id', 'team_id'], 'team_tier_signal_audit_target_idx');
        });

        Schema::create('ai_team_tier_audit_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_team_tier_audit_run_id')
                ->constrained('ai_team_tier_audit_runs')
                ->cascadeOnDelete();
            $table->string('signal_key', 100);
            $table->string('label', 160);
            $table->unsignedInteger('sample_count');
            $table->decimal('pearson_correlation', 9, 6)->nullable();
            $table->decimal('absolute_correlation', 9, 6)->nullable();
            $table->timestamps();

            $table->unique(
                ['ai_team_tier_audit_run_id', 'signal_key'],
                'team_tier_signal_audit_metric_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_team_tier_audit_metrics');
        Schema::dropIfExists('ai_team_tier_audit_observations');
        Schema::dropIfExists('ai_team_tier_audit_runs');
    }
};
