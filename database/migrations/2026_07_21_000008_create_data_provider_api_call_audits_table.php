<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_provider_api_call_audits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('data_provider_id')->constrained('data_providers')->cascadeOnDelete();
            $table->foreignId('data_provider_http_endpoint_id')->nullable()->constrained('data_provider_http_endpoints')->nullOnDelete();
            $table->string('provider_code', 80);
            $table->string('capability', 80);
            $table->string('operation', 80);
            $table->string('method', 10);
            $table->string('endpoint_template', 500)->nullable();
            $table->string('resolved_endpoint', 500)->nullable();
            $table->string('query_fingerprint', 64)->nullable();
            $table->string('request_fingerprint', 64);
            $table->string('provider_request_id', 255)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('items_count')->default(0);
            $table->string('response_fingerprint', 64)->nullable();
            $table->string('error_code', 120)->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->string('sync_type', 120)->nullable();
            $table->string('sync_target_type', 120)->nullable();
            $table->unsignedBigInteger('sync_target_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('called_at')->useCurrent();
            $table->timestamps();

            $table->index(['provider_code', 'capability', 'operation'], 'provider_api_call_audit_lookup');
            $table->index(['sync_type', 'sync_target_type', 'sync_target_id'], 'provider_api_call_audit_sync_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_provider_api_call_audits');
    }
};
