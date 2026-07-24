<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('team_tier_settings')->updateOrInsert(
            [
                'setting_group' => 'auto_tuning_experiments',
                'setting_key' => 'incremental_grid',
            ],
            [
                'value' => json_encode([
                    'metric_profiles' => [
                        'gd00' => ['points' => 0.48, 'goals_for' => 0.34, 'goals_against' => 0.18, 'goal_difference' => 0.00],
                        'gd05' => ['points' => 0.45, 'goals_for' => 0.33, 'goals_against' => 0.17, 'goal_difference' => 0.05],
                        'gd10' => ['points' => 0.43, 'goals_for' => 0.31, 'goals_against' => 0.16, 'goal_difference' => 0.10],
                        'gd15' => ['points' => 0.40, 'goals_for' => 0.30, 'goals_against' => 0.15, 'goal_difference' => 0.15],
                    ],
                    'promotion_profiles' => [
                        'fixed' => ['enabled' => false],
                        'dyn110_130' => ['enabled' => true, 'min' => 1.10, 'max' => 1.30],
                        'dyn115_130' => ['enabled' => true, 'min' => 1.15, 'max' => 1.30],
                        'dyn115_135' => ['enabled' => true, 'min' => 1.15, 'max' => 1.35],
                        'dyn120_135' => ['enabled' => true, 'min' => 1.20, 'max' => 1.35],
                    ],
                    'volatility_factors' => [0.00, 0.10, 0.20, 0.30, 0.50],
                ]),
                'data_type' => 'json',
                'label' => 'Griglia esperimenti incrementali',
                'description' => 'Profili DB usati per verificare differenza reti, neopromosse dinamiche e volatilita.',
                'sort_order' => 190,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        Schema::table('ai_team_tier_tuning_candidates', function (Blueprint $table): void {
            $table->string('reference_profile_key', 100)->nullable()->after('profile_key');
        });
    }

    public function down(): void
    {
        Schema::table('ai_team_tier_tuning_candidates', function (Blueprint $table): void {
            $table->dropColumn('reference_profile_key');
        });

        DB::table('team_tier_settings')
            ->where('setting_group', 'auto_tuning_experiments')
            ->where('setting_key', 'incremental_grid')
            ->delete();
    }
};
