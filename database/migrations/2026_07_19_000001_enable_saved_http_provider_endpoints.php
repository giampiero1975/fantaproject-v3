<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('data_provider_http_endpoints')
            ->whereExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('data_provider_payload_mappings')
                    ->whereColumn('data_provider_payload_mappings.data_provider_http_endpoint_id', 'data_provider_http_endpoints.id');
            })
            ->update([
                'is_enabled' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // No-op: older endpoint enabled/disabled choices cannot be reconstructed safely.
    }
};
