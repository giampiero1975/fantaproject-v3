<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $fieldRenames = [
        'provider_competition_key' => 'provider_competition_code',
        'logo_url' => 'competition_logo_url',
    ];

    public function up(): void
    {
        $this->renameContractFields($this->fieldRenames);

        DB::table('data_provider_contract_fields')
            ->where('capability', 'competitions')
            ->where('field_key', 'provider_competition_code')
            ->update([
                'label' => 'Chiave competizione provider',
                'description' => 'Codice o chiave stabile usata dal provider per identificare la competizione nelle chiamate API. {"code":"SA"}',
                'updated_at' => now(),
            ]);

        DB::table('data_provider_contract_fields')
            ->where('capability', 'competitions')
            ->where('field_key', 'competition_logo_url')
            ->update([
                'label' => 'Logo della competizione',
                'description' => 'URL logo o emblema della competizione. {"emblem":"https://crests.football-data.org/c111.png"}',
                'data_type' => 'url',
                'is_required' => false,
                'sort_order' => 70,
                'updated_at' => now(),
            ]);

        $this->renameSavedPayloadMappings($this->fieldRenames);
    }

    public function down(): void
    {
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
