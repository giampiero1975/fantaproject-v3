<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $fieldRenames = [
        'external_id' => 'provider_competition_key',
        'provider_numeric_id' => 'provider_competition_id',
        'name' => 'competition_name',
        'country' => 'country_name',
        'type' => 'competition_type',
    ];

    public function up(): void
    {
        $this->renameContractFields($this->fieldRenames);

        DB::table('data_provider_contract_fields')
            ->where('capability', 'competitions')
            ->where('field_key', 'provider_competition_key')
            ->update([
                'label' => 'Chiave competizione provider',
                'description' => 'Codice o chiave stabile usata dal provider per identificare la competizione nelle chiamate API.',
                'updated_at' => now(),
            ]);

        DB::table('data_provider_contract_fields')
            ->where('capability', 'competitions')
            ->where('field_key', 'provider_competition_id')
            ->update([
                'label' => 'ID competizione provider',
                'description' => 'Identificatore numerico della competizione nel provider, se disponibile.',
                'updated_at' => now(),
            ]);

        DB::table('data_provider_contract_fields')
            ->where('capability', 'competitions')
            ->where('field_key', 'competition_name')
            ->update([
                'label' => 'Nome competizione',
                'updated_at' => now(),
            ]);

        DB::table('data_provider_contract_fields')
            ->where('capability', 'competitions')
            ->where('field_key', 'country_name')
            ->update([
                'label' => 'Paese',
                'description' => 'Nome del paese o area geografica della competizione.',
                'updated_at' => now(),
            ]);

        DB::table('data_provider_contract_fields')
            ->where('capability', 'competitions')
            ->where('field_key', 'competition_type')
            ->update([
                'label' => 'Tipo competizione',
                'updated_at' => now(),
            ]);

        DB::table('data_provider_contract_fields')->updateOrInsert(
            [
                'capability' => 'competitions',
                'field_key' => 'provider_area_id',
            ],
            [
                'label' => 'ID area provider',
                'description' => 'Identificatore numerico dell area o paese nel provider, distinto dall ID della competizione.',
                'data_type' => 'integer',
                'is_required' => false,
                'sort_order' => 45,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->renameSavedPayloadMappings($this->fieldRenames);
    }

    public function down(): void
    {
        DB::table('data_provider_contract_fields')
            ->where('capability', 'competitions')
            ->where('field_key', 'provider_area_id')
            ->delete();

        $this->renameSavedPayloadMappings(array_flip($this->fieldRenames));
        $this->renameContractFields(array_flip($this->fieldRenames));
    }

    /**
     * @param  array<string, string>  $renames
     */
    private function renameContractFields(array $renames): void
    {
        foreach ($renames as $from => $to) {
            DB::table('data_provider_contract_fields')
                ->where('capability', 'competitions')
                ->where('field_key', $from)
                ->update([
                    'field_key' => $to,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * @param  array<string, string>  $renames
     */
    private function renameSavedPayloadMappings(array $renames): void
    {
        $mappings = DB::table('data_provider_payload_mappings as m')
            ->join('data_provider_http_endpoints as e', 'e.id', '=', 'm.data_provider_http_endpoint_id')
            ->where('e.capability', 'competitions')
            ->get([
                'm.id',
                'm.field_mappings',
                'm.required_fields',
            ]);

        foreach ($mappings as $mapping) {
            $fieldMappings = json_decode((string) $mapping->field_mappings, true) ?: [];
            $requiredFields = json_decode((string) $mapping->required_fields, true) ?: [];

            foreach ($renames as $from => $to) {
                if (array_key_exists($from, $fieldMappings)) {
                    $fieldMappings[$to] = $fieldMappings[$from];
                    unset($fieldMappings[$from]);
                }

                $requiredFields = array_map(
                    fn (string $field): string => $field === $from ? $to : $field,
                    $requiredFields
                );
            }

            DB::table('data_provider_payload_mappings')
                ->where('id', $mapping->id)
                ->update([
                    'field_mappings' => json_encode($fieldMappings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'required_fields' => json_encode(array_values(array_unique($requiredFields)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
        }
    }
};
