<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('season_id')->constrained('seasons')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['league_id', 'season_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_seasons');
    }
};
