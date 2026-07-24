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
                'setting_group' => 'normalization',
                'setting_key' => 'goal_difference_per_game',
            ],
            [
                'value' => json_encode(['min' => -2.50, 'max' => 2.50]),
                'data_type' => 'json',
                'label' => 'Normalizzazione differenza reti',
                'description' => 'Intervallo usato dagli esperimenti incrementali per normalizzare la differenza reti per partita.',
                'sort_order' => 180,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        Schema::table('ai_team_tier_tuning_candidates', function (Blueprint $table): void {
            $table->decimal('goal_difference_weight', 7, 4)->default(0)->after('goals_against_weight');
            $table->boolean('promoted_dynamic_enabled')->default(false)->after('promoted_penalty');
            $table->decimal('promoted_penalty_min', 7, 4)->nullable()->after('promoted_dynamic_enabled');
            $table->decimal('promoted_penalty_max', 7, 4)->nullable()->after('promoted_penalty_min');
            $table->decimal('volatility_penalty_factor', 7, 4)->default(0)->after('promoted_penalty_max');
        });
    }

    public function down(): void
    {
        Schema::table('ai_team_tier_tuning_candidates', function (Blueprint $table): void {
            $table->dropColumn([
                'goal_difference_weight',
                'promoted_dynamic_enabled',
                'promoted_penalty_min',
                'promoted_penalty_max',
                'volatility_penalty_factor',
            ]);
        });

        DB::table('team_tier_settings')
            ->where('setting_group', 'normalization')
            ->where('setting_key', 'goal_difference_per_game')
            ->delete();
    }
};
