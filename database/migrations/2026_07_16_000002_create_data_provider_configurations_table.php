<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_provider_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_provider_id')->constrained('data_providers')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('key', 120);
            $table->text('value')->nullable();
            $table->string('value_type', 30)->default('string');
            $table->string('environment', 30)->nullable();
            $table->boolean('is_secret')->default(false);
            $table->timestamps();

            $table->unique(['data_provider_id', 'key', 'environment'], 'provider_configuration_unique');
            $table->index(['key', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_provider_configurations');
    }
};
