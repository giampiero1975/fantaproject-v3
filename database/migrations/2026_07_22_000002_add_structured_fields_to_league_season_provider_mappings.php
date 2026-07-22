<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_season_provider_mappings', function (Blueprint $table): void {
            if (! Schema::hasColumn('league_season_provider_mappings', 'external_season_id')) {
                $table->string('external_season_id', 100)->nullable();
            }

            if (! Schema::hasColumn('league_season_provider_mappings', 'external_start_date')) {
                $table->date('external_start_date')->nullable();
            }

            if (! Schema::hasColumn('league_season_provider_mappings', 'external_end_date')) {
                $table->date('external_end_date')->nullable();
            }
        });

        if (Schema::hasColumn('league_season_provider_mappings', 'metadata')) {
            DB::table('league_season_provider_mappings')
                ->whereNotNull('metadata')
                ->orderBy('id')
                ->get(['id', 'metadata'])
                ->each(function (object $mapping): void {
                    $metadata = json_decode((string) $mapping->metadata, true);

                    if (! is_array($metadata)) {
                        return;
                    }

                    DB::table('league_season_provider_mappings')
                        ->where('id', $mapping->id)
                        ->update([
                            'external_season_id' => $metadata['season_external_id'] ?? null,
                            'external_start_date' => $metadata['start_date'] ?? null,
                            'external_end_date' => $metadata['end_date'] ?? null,
                            'metadata' => null,
                        ]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('league_season_provider_mappings', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['external_season_id', 'external_start_date', 'external_end_date'],
                fn (string $column): bool => Schema::hasColumn('league_season_provider_mappings', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
