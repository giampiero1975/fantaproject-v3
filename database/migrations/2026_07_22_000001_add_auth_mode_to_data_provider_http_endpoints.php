<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_provider_http_endpoints', function (Blueprint $table): void {
            $table->string('auth_mode', 20)->default('default')->after('method');
        });
    }

    public function down(): void
    {
        Schema::table('data_provider_http_endpoints', function (Blueprint $table): void {
            $table->dropColumn('auth_mode');
        });
    }
};
