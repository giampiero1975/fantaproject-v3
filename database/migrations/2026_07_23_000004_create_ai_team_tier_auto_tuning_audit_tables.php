<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach ($this->settings() as $setting) {
            DB::table('team_tier_settings')->updateOrInsert(
                [
                    'setting_group' => $setting['setting_group'],
                    'setting_key' => $setting['setting_key'],
                ],
                $setting + [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        Schema::create('ai_team_tier_tuning_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ai_team_tier_audit_run_id')
                ->nullable()
                ->constrained('ai_team_tier_audit_runs')
                ->nullOnDelete();
            $table->unsignedInteger('seasons_count');
            $table->string('validation_status', 40);
            $table->decimal('min_average_ranking_uplift_pct', 7, 3);
            $table->decimal('max_single_season_ranking_drop_pct', 7, 3);
            $table->decimal('max_position_mae_increase', 7, 3);
            $table->timestamps();
        });

        Schema::create('ai_team_tier_tuning_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_team_tier_tuning_run_id')
                ->constrained('ai_team_tier_tuning_runs')
                ->cascadeOnDelete();
            $table->string('profile_key', 100);
            $table->string('label', 160);
            $table->boolean('is_active_profile')->default(false);
            $table->decimal('points_weight', 7, 4);
            $table->decimal('goals_for_weight', 7, 4);
            $table->decimal('goals_against_weight', 7, 4);
            $table->decimal('position_weight', 7, 4)->default(0);
            $table->decimal('historical_weight', 7, 4);
            $table->decimal('momentum_weight', 7, 4);
            $table->decimal('promoted_penalty', 7, 4);
            $table->decimal('ranking_accuracy_pct', 7, 3);
            $table->decimal('position_mae', 7, 3);
            $table->decimal('exact_tier_pct', 7, 3);
            $table->decimal('within_one_tier_pct', 7, 3);
            $table->decimal('ranking_uplift_pct', 7, 3);
            $table->decimal('position_mae_delta', 7, 3);
            $table->boolean('accepted')->default(false);
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['ai_team_tier_tuning_run_id', 'profile_key'],
                'ai_team_tier_tuning_candidate_unique'
            );
        });

        Schema::create('ai_team_tier_tuning_candidate_seasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_team_tier_tuning_candidate_id')
                ->constrained('ai_team_tier_tuning_candidates')
                ->cascadeOnDelete();
            $table->foreignId('league_season_id')->constrained()->cascadeOnDelete();
            $table->decimal('ranking_accuracy_pct', 7, 3);
            $table->decimal('position_mae', 7, 3);
            $table->decimal('exact_tier_pct', 7, 3);
            $table->decimal('within_one_tier_pct', 7, 3);
            $table->decimal('ranking_uplift_pct', 7, 3);
            $table->timestamps();

            $table->unique(
                ['ai_team_tier_tuning_candidate_id', 'league_season_id'],
                'ai_team_tier_tuning_candidate_season_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_team_tier_tuning_candidate_seasons');
        Schema::dropIfExists('ai_team_tier_tuning_candidates');
        Schema::dropIfExists('ai_team_tier_tuning_runs');

        DB::table('team_tier_settings')
            ->whereIn('setting_group', ['auto_tuning_profiles', 'auto_tuning_guards'])
            ->delete();
    }

    /** @return list<array<string,mixed>> */
    private function settings(): array
    {
        return [
            [
                'setting_group' => 'auto_tuning_profiles',
                'setting_key' => 'legacy_baseline',
                'value' => json_encode([
                    'weights' => [
                        'metrics' => ['points' => 0.60, 'goals_for' => 0.28, 'goals_against' => 0.12],
                        'fusion' => ['historical' => 0.70, 'momentum' => 0.30],
                    ],
                    'transition_penalties' => [
                        'by_case' => ['promoted_from_lower_league' => 1.25],
                    ],
                ]),
                'data_type' => 'json',
                'label' => 'Profilo baseline auto-tuning',
                'description' => 'Profilo precedente usato come baseline riproducibile negli audit di auto-tuning.',
                'sort_order' => 160,
            ],
            [
                'setting_group' => 'auto_tuning_guards',
                'setting_key' => 'acceptance',
                'value' => json_encode([
                    'min_average_ranking_uplift_pct' => 0.10,
                    'max_single_season_ranking_drop_pct' => 0.00,
                    'max_position_mae_increase' => 0.00,
                ]),
                'data_type' => 'json',
                'label' => 'Guardrail auto-tuning',
                'description' => 'Condizioni minime per validare un profilo candidato senza overfitting evidente.',
                'sort_order' => 170,
            ],
        ];
    }
};
