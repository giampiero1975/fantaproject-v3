<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_provider_http_endpoints', function (Blueprint $table) {
            $table->string('operation', 50)->default('list')->after('capability');
        });

        Schema::table('data_provider_http_endpoints', function (Blueprint $table) {
            $table->dropUnique('provider_http_endpoint_unique');
            $table->unique(['data_provider_id', 'capability', 'operation'], 'provider_http_endpoint_operation_unique');
        });
    }

    public function down(): void
    {
        Schema::table('data_provider_http_endpoints', function (Blueprint $table) {
            $table->dropUnique('provider_http_endpoint_operation_unique');
            $table->unique(['data_provider_id', 'capability'], 'provider_http_endpoint_unique');
        });

        Schema::table('data_provider_http_endpoints', function (Blueprint $table) {
            $table->dropColumn('operation');
        });
    }
};
