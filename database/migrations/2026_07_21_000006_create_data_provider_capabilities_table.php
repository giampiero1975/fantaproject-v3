<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_provider_capabilities', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('label', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_runtime_configurable')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('data_provider_capabilities')->insert([
            [
                'key' => 'countries',
                'label' => 'Nazioni',
                'description' => 'Catalogo nazioni o aree geografiche esposte dal provider.',
                'is_active' => true,
                'is_runtime_configurable' => true,
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'competitions',
                'label' => 'Competizioni',
                'description' => 'Competizioni, leghe e tornei censiti dal provider.',
                'is_active' => true,
                'is_runtime_configurable' => true,
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'seasons',
                'label' => 'Stagioni',
                'description' => 'Timeline stagioni disponibili per una competizione.',
                'is_active' => true,
                'is_runtime_configurable' => true,
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'teams',
                'label' => 'Squadre',
                'description' => 'Squadre iscritte o disponibili per competizione e stagione.',
                'is_active' => true,
                'is_runtime_configurable' => true,
                'sort_order' => 40,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'standings',
                'label' => 'Classifiche',
                'description' => 'Classifiche e posizioni stagionali delle squadre.',
                'is_active' => true,
                'is_runtime_configurable' => true,
                'sort_order' => 50,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'fixtures',
                'label' => 'Partite',
                'description' => 'Calendario, risultati e partite disputate o programmate.',
                'is_active' => true,
                'is_runtime_configurable' => false,
                'sort_order' => 60,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'players',
                'label' => 'Giocatori',
                'description' => 'Anagrafiche e rose giocatori.',
                'is_active' => true,
                'is_runtime_configurable' => false,
                'sort_order' => 70,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'statistics',
                'label' => 'Statistiche',
                'description' => 'Metriche statistiche avanzate o aggregate.',
                'is_active' => true,
                'is_runtime_configurable' => false,
                'sort_order' => 80,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('data_provider_capabilities');
    }
};
