<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_provider_capability_contexts', function (Blueprint $table): void {
            $table->id();
            $table->string('context_key', 100);
            $table->foreignId('data_provider_capability_id')
                ->constrained('data_provider_capabilities')
                ->cascadeOnDelete();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['context_key', 'data_provider_capability_id'], 'provider_capability_context_unique');
        });

        $now = now();
        $capabilities = DB::table('data_provider_capabilities')
            ->whereIn('key', ['competitions', 'seasons', 'teams'])
            ->pluck('id', 'key');

        foreach (['competitions' => 10, 'seasons' => 20, 'teams' => 30] as $key => $sortOrder) {
            if (! $capabilities->has($key)) {
                continue;
            }

            DB::table('data_provider_capability_contexts')->insert([
                'context_key' => 'season_management',
                'data_provider_capability_id' => $capabilities[$key],
                'is_required' => true,
                'sort_order' => $sortOrder,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('data_provider_capability_contexts');
    }
};
