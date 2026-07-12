<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_provider_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')
                ->constrained('leagues')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('data_provider_id')
                ->constrained('data_providers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('external_id', 100);
            $table->string('external_name', 180);
            $table->string('external_country', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['data_provider_id', 'external_id'], 'provider_external_unique');
            $table->unique(['league_id', 'data_provider_id'], 'league_provider_unique');
            $table->index(['data_provider_id', 'verified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_provider_mappings');
    }
};
