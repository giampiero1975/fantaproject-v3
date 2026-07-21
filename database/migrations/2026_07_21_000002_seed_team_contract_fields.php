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
            ->where('capability', 'teams')
            ->whereIn('operation', ['by_season', 'by_competition'])
            ->whereIn('field_key', [
                'provider_team_id',
                'team_name',
                'short_name',
                'team_code',
                'country_name',
                'crest_url',
            ])
            ->delete();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function fields(string $operation, mixed $now): array
    {
        return [
            [
                'capability' => 'teams',
                'operation' => $operation,
                'field_key' => 'provider_team_id',
                'label' => 'ID squadra provider',
                'description' => 'Identificatore stabile della squadra nel provider. Football-Data: {"id":109}.',
                'data_type' => 'string',
                'is_required' => true,
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'capability' => 'teams',
                'operation' => $operation,
                'field_key' => 'team_name',
                'label' => 'Nome squadra',
                'description' => 'Nome leggibile della squadra restituito dal provider. Football-Data: {"name":"Juventus FC"}.',
                'data_type' => 'string',
                'is_required' => true,
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'capability' => 'teams',
                'operation' => $operation,
                'field_key' => 'short_name',
                'label' => 'Nome breve',
                'description' => 'Nome abbreviato o operativo della squadra. Football-Data: {"shortName":"Juve"}.',
                'data_type' => 'string',
                'is_required' => false,
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'capability' => 'teams',
                'operation' => $operation,
                'field_key' => 'team_code',
                'label' => 'Codice squadra',
                'description' => 'Codice breve della squadra, se disponibile. Football-Data: {"tla":"JUV"}.',
                'data_type' => 'string',
                'is_required' => false,
                'sort_order' => 40,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'capability' => 'teams',
                'operation' => $operation,
                'field_key' => 'country_name',
                'label' => 'Paese',
                'description' => 'Paese della squadra, se il provider lo espone.',
                'data_type' => 'string',
                'is_required' => false,
                'sort_order' => 50,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'capability' => 'teams',
                'operation' => $operation,
                'field_key' => 'crest_url',
                'label' => 'Logo squadra',
                'description' => 'URL stemma o logo della squadra. Football-Data: {"crest":"https://..."}.',
                'data_type' => 'url',
                'is_required' => false,
                'sort_order' => 60,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
    }
};
