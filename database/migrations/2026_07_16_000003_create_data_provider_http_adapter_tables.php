<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_provider_http_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_provider_id')->constrained('data_providers')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('capability', 50);
            $table->string('method', 10)->default('GET');
            $table->string('endpoint', 500);
            $table->json('query_params')->nullable();
            $table->json('body_template')->nullable();
            $table->string('items_path', 250)->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->string('validation_status', 50)->default('draft');
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->json('sample_payload')->nullable();
            $table->json('sample_normalized')->nullable();
            $table->timestamps();

            $table->unique(['data_provider_id', 'capability'], 'provider_http_endpoint_unique');
        });

        Schema::create('data_provider_payload_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_provider_http_endpoint_id')->constrained('data_provider_http_endpoints')->cascadeOnUpdate()->cascadeOnDelete();
            $table->json('field_mappings');
            $table->json('required_fields');
            $table->string('validation_status', 50)->default('mapping_incomplete');
            $table->timestamps();

            $table->unique('data_provider_http_endpoint_id', 'provider_payload_mapping_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_provider_payload_mappings');
        Schema::dropIfExists('data_provider_http_endpoints');
    }
};
