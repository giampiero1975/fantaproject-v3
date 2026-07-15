<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_provider_runtime_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_provider_id')->constrained('data_providers')->cascadeOnUpdate()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->string('role', 30)->default('fallback');
            $table->string('base_url', 500);
            $table->unsignedSmallInteger('timeout')->default(30);
            $table->unsignedSmallInteger('connect_timeout')->default(10);
            $table->unsignedSmallInteger('retry_times')->default(3);
            $table->unsignedInteger('retry_sleep_ms')->default(500);
            $table->string('plan', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique('data_provider_id');
            $table->index(['is_enabled', 'priority']);
        });

        Schema::create('data_provider_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_provider_id')->constrained('data_providers')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('environment', 30)->default('production');
            $table->string('credential_key', 100);
            $table->text('encrypted_value');
            $table->boolean('is_active')->default(true);
            $table->timestamp('rotated_at')->nullable();
            $table->timestamps();
            $table->unique(['data_provider_id', 'environment', 'credential_key'], 'provider_credential_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_provider_credentials');
        Schema::dropIfExists('data_provider_runtime_configs');
    }
};