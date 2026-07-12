<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leagues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')
                ->constrained('countries')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('name', 180);
            $table->string('slug', 220)->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['country_id', 'name']);
            $table->index(['country_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leagues');
    }
};
