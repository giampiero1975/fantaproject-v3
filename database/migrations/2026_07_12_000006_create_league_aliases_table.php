<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_aliases', function (Blueprint $table) {
            $table->id();

            $table->foreignId('league_id')
                ->constrained('leagues')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // NULL = alias generico Fanta Oracle.
            // Valorizzato = alias specifico di un provider.
            $table->foreignId('data_provider_id')
                ->nullable()
                ->constrained('data_providers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('alias', 180);
            $table->string('normalized_alias', 180);
            $table->string('source', 40)->default('manual');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(
                ['league_id', 'data_provider_id', 'normalized_alias'],
                'league_aliases_unique'
            );

            $table->index(
                ['data_provider_id', 'normalized_alias', 'active'],
                'league_aliases_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_aliases');
    }
};
