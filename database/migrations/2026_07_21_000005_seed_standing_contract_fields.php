<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (['by_season', 'by_competition'] as $operation) {
            foreach ($this->fields($operation, $now) as $field) {
                DB::table('data_provider_contract_fields')->updateOrInsert(
                    [
                        'capability' => $field['capability'],
                        'operation' => $field['operation'],
                        'field_key' => $field['field_key'],
                    ],
                    $field,
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('data_provider_contract_fields')
            ->where('capability', 'standings')
            ->whereIn('operation', ['by_season', 'by_competition'])
            ->whereIn('field_key', [
                'provider_team_id', 'team_name', 'team_code', 'position', 'played_games',
                'won', 'draw', 'lost', 'points', 'goals_for', 'goals_against',
                'goal_difference', 'stage_name', 'group_name',
            ])
            ->delete();
    }

    /** @return list<array<string,mixed>> */
    private function fields(string $operation, mixed $now): array
    {
        $base = [
            ['provider_team_id', 'ID squadra provider', 'Identificatore della squadra nel provider. Football-Data: {"team":{"id":109}}.', 'string', true, 10],
            ['team_name', 'Nome squadra', 'Nome squadra nella riga classifica. Football-Data: {"team":{"name":"Juventus FC"}}.', 'string', true, 20],
            ['team_code', 'Codice squadra', 'Codice breve squadra, se disponibile. Football-Data: {"team":{"tla":"JUV"}}.', 'string', false, 30],
            ['position', 'Posizione', 'Posizione in classifica. Football-Data: {"position":1}.', 'integer', false, 40],
            ['played_games', 'Partite giocate', 'Partite giocate. Football-Data: {"playedGames":38}.', 'integer', false, 50],
            ['won', 'Vittorie', 'Numero vittorie.', 'integer', false, 60],
            ['draw', 'Pareggi', 'Numero pareggi.', 'integer', false, 70],
            ['lost', 'Sconfitte', 'Numero sconfitte.', 'integer', false, 80],
            ['points', 'Punti', 'Punti in classifica.', 'integer', false, 90],
            ['goals_for', 'Gol fatti', 'Gol segnati.', 'integer', false, 100],
            ['goals_against', 'Gol subiti', 'Gol subiti.', 'integer', false, 110],
            ['goal_difference', 'Differenza reti', 'Differenza reti.', 'integer', false, 120],
            ['stage_name', 'Fase', 'Nome fase o stage, se disponibile.', 'string', false, 130],
            ['group_name', 'Girone', 'Nome gruppo o girone, se disponibile.', 'string', false, 140],
        ];

        return collect($base)
            ->map(fn (array $field): array => [
                'capability' => 'standings',
                'operation' => $operation,
                'field_key' => $field[0],
                'label' => $field[1],
                'description' => $field[2],
                'data_type' => $field[3],
                'is_required' => $field[4],
                'sort_order' => $field[5],
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();
    }
};