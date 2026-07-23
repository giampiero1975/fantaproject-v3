<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_provider_api_call_audits', function (Blueprint $table): void {
            $table->json('resolved_query')->nullable()->after('resolved_endpoint');
            $table->json('provider_headers')->nullable()->after('response_fingerprint');
        });

        Schema::table('data_provider_api_call_audits', function (Blueprint $table): void {
            $table->dropColumn([
                'query_fingerprint',
                'request_fingerprint',
                'provider_request_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('data_provider_api_call_audits', function (Blueprint $table): void {
            $table->string('query_fingerprint', 64)->nullable()->after('resolved_endpoint');
            $table->string('request_fingerprint', 64)->nullable()->after('query_fingerprint');
            $table->string('provider_request_id', 255)->nullable()->after('request_fingerprint');
        });

        Schema::table('data_provider_api_call_audits', function (Blueprint $table): void {
            $table->dropColumn([
                'resolved_query',
                'provider_headers',
            ]);
        });
    }
};
