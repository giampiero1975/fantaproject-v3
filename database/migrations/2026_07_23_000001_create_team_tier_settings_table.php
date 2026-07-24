<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_tier_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('setting_group', 80);
            $table->string('setting_key', 120);
            $table->json('value');
            $table->string('data_type', 40)->default('json');
            $table->string('label', 160);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['setting_group', 'setting_key'], 'team_tier_settings_unique');
        });

        $now = now();
        foreach ($this->settings() as $setting) {
            DB::table('team_tier_settings')->insert($setting + [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('team_tier_settings');
    }

    /** @return list<array<string,mixed>> */
    private function settings(): array
    {
        return [
            [
                'setting_group' => 'lookback',
                'setting_key' => 'historical',
                'value' => json_encode(4),
                'data_type' => 'integer',
                'label' => 'Lookback storico',
                'description' => 'Numero di stagioni concluse usate dalla traccia storica.',
                'sort_order' => 10,
            ],
            [
                'setting_group' => 'lookback',
                'setting_key' => 'momentum',
                'value' => json_encode(2),
                'data_type' => 'integer',
                'label' => 'Lookback momentum',
                'description' => 'Numero di stagioni recenti usate dalla traccia momentum.',
                'sort_order' => 20,
            ],
            [
                'setting_group' => 'weights',
                'setting_key' => 'historical',
                'value' => json_encode([12, 4, 2, 1]),
                'data_type' => 'json',
                'label' => 'Pesi storico',
                'description' => 'Pesi decrescenti della traccia storica, dalla stagione piu recente alla meno recente.',
                'sort_order' => 30,
            ],
            [
                'setting_group' => 'weights',
                'setting_key' => 'momentum',
                'value' => json_encode([10, 4]),
                'data_type' => 'json',
                'label' => 'Pesi momentum',
                'description' => 'Pesi della traccia momentum.',
                'sort_order' => 40,
            ],
            [
                'setting_group' => 'weights',
                'setting_key' => 'fusion',
                'value' => json_encode(['historical' => 0.65, 'momentum' => 0.35]),
                'data_type' => 'json',
                'label' => 'Fusione storico/momentum',
                'description' => 'Peso finale delle due tracce nel punteggio tier, validato tramite audit walk-forward.',
                'sort_order' => 50,
            ],
            [
                'setting_group' => 'weights',
                'setting_key' => 'metrics',
                'value' => json_encode(['points' => 0.48, 'goals_for' => 0.34, 'goals_against' => 0.18]),
                'data_type' => 'json',
                'label' => 'Pesi metriche',
                'description' => 'Pesi interni dello score stagionale: punti, gol fatti, gol subiti. Profilo validato tramite audit walk-forward.',
                'sort_order' => 60,
            ],
            [
                'setting_group' => 'divisors',
                'setting_key' => 'historical',
                'value' => json_encode(19.0),
                'data_type' => 'decimal',
                'label' => 'Divisore storico',
                'description' => 'Divisore della traccia storica V2.',
                'sort_order' => 70,
            ],
            [
                'setting_group' => 'divisors',
                'setting_key' => 'momentum',
                'value' => json_encode(14.0),
                'data_type' => 'decimal',
                'label' => 'Divisore momentum',
                'description' => 'Divisore della traccia momentum V2.',
                'sort_order' => 80,
            ],
            [
                'setting_group' => 'thresholds',
                'setting_key' => 'by_tier',
                'value' => json_encode(['1' => 7.5, '2' => 9.5, '3' => 12.5, '4' => 13.5]),
                'data_type' => 'json',
                'label' => 'Soglie tier',
                'description' => 'Soglie massime del punteggio: score piu basso significa squadra piu forte.',
                'sort_order' => 90,
            ],
            [
                'setting_group' => 'league_multipliers',
                'setting_key' => 'default',
                'value' => json_encode(['points' => 1.60, 'goals_for' => 1.60, 'goals_against' => 1.00]),
                'data_type' => 'json',
                'label' => 'Moltiplicatori default lega',
                'description' => 'Moltiplicatori applicati quando la lega non ha una configurazione specifica.',
                'sort_order' => 100,
            ],
            [
                'setting_group' => 'league_multipliers',
                'setting_key' => 'Serie A',
                'value' => json_encode(['points' => 1.00, 'goals_for' => 1.00, 'goals_against' => 1.00]),
                'data_type' => 'json',
                'label' => 'Moltiplicatori Serie A',
                'description' => 'Serie A non applica penalita di lega.',
                'sort_order' => 110,
            ],
            [
                'setting_group' => 'league_multipliers',
                'setting_key' => 'Serie B',
                'value' => json_encode(['points' => 1.60, 'goals_for' => 1.60, 'goals_against' => 1.00]),
                'data_type' => 'json',
                'label' => 'Moltiplicatori Serie B',
                'description' => 'Penalita lineare V2 per stagioni disputate in Serie B.',
                'sort_order' => 120,
            ],
            [
                'setting_group' => 'rules',
                'setting_key' => 'missing_season_score',
                'value' => json_encode(20.0),
                'data_type' => 'decimal',
                'label' => 'Score stagione mancante',
                'description' => 'Peggior punteggio possibile quando manca una stagione nel lookback.',
                'sort_order' => 130,
            ],
            [
                'setting_group' => 'rules',
                'setting_key' => 'trend_penalty',
                'value' => json_encode(1.05),
                'data_type' => 'decimal',
                'label' => 'Penalita trend negativo',
                'description' => 'Moltiplicatore applicato in caso di peggioramento continuo delle ultime posizioni Serie A.',
                'sort_order' => 140,
            ],
            [
                'setting_group' => 'transition_penalties',
                'setting_key' => 'by_case',
                'value' => json_encode(['promoted_from_lower_league' => 1.25]),
                'data_type' => 'json',
                'label' => 'Penalita transizione lega',
                'description' => 'Moltiplicatori applicati quando una squadra arriva da una lega diversa da quella target. Usato per ridurre la sovrastima delle neopromosse.',
                'sort_order' => 150,
            ],
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
            [
                'setting_group' => 'normalization',
                'setting_key' => 'goal_difference_per_game',
                'value' => json_encode(['min' => -2.50, 'max' => 2.50]),
                'data_type' => 'json',
                'label' => 'Normalizzazione differenza reti',
                'description' => 'Intervallo usato dagli esperimenti incrementali per normalizzare la differenza reti per partita.',
                'sort_order' => 180,
            ],
            [
                'setting_group' => 'auto_tuning_experiments',
                'setting_key' => 'incremental_grid',
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
            ],
        ];
    }
};
