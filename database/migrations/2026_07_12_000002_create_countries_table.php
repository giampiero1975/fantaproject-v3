<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('confederation_id')
                ->constrained('confederations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('region', 80);
            $table->string('name', 120);
            $table->string('iso2', 8);
            $table->string('iso3', 8);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['confederation_id', 'iso3']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
