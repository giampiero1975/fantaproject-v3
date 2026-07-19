<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->configure('football_data', [
            'auth_type' => 'header',
            'credential_key' => 'token',
            'auth_header_name' => 'X-Auth-Token',
            'auth_query_param' => null,
        ]);

        $this->configure('api_football', [
            'auth_type' => 'header',
            'credential_key' => 'api_key',
            'auth_header_name' => 'x-apisports-key',
            'auth_query_param' => null,
        ]);
    }

    public function down(): void
    {
        $providerIds = DB::table('data_providers')
            ->whereIn('code', ['football_data', 'api_football'])
            ->pluck('id');

        DB::table('data_provider_configurations')
            ->whereIn('data_provider_id', $providerIds)
            ->whereIn('key', ['auth_type', 'credential_key', 'auth_header_name', 'auth_query_param'])
            ->delete();
    }

    /**
     * @param  array<string, string|null>  $settings
     */
    private function configure(string $providerCode, array $settings): void
    {
        $providerId = DB::table('data_providers')
            ->where('code', $providerCode)
            ->value('id');

        if (! $providerId) {
            return;
        }

        foreach ($settings as $key => $value) {
            DB::table('data_provider_configurations')->updateOrInsert(
                [
                    'data_provider_id' => $providerId,
                    'key' => $key,
                    'environment' => null,
                ],
                [
                    'value' => $value,
                    'value_type' => 'string',
                    'is_secret' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
};
