<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_provider_http_endpoints', function (Blueprint $table): void {
            if (! Schema::hasColumn('data_provider_http_endpoints', 'label')) {
                $table->string('label', 150)->nullable()->after('operation');
            }
        });
    }

    public function down(): void
    {
        Schema::table('data_provider_http_endpoints', function (Blueprint $table): void {
            if (Schema::hasColumn('data_provider_http_endpoints', 'label')) {
                $table->dropColumn('label');
            }
        });
    }
};
