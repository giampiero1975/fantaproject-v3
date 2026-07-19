<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_provider_contract_fields', function (Blueprint $table) {
            $table->dropUnique('provider_contract_field_unique');
            $table->dropIndex('provider_contract_field_order_idx');

            $table->string('operation', 50)->default('list')->after('capability');

            $table->unique(['capability', 'operation', 'field_key'], 'provider_contract_field_unique');
            $table->index(['capability', 'operation', 'sort_order'], 'provider_contract_field_order_idx');
        });
    }

    public function down(): void
    {
        Schema::table('data_provider_contract_fields', function (Blueprint $table) {
            $table->dropUnique('provider_contract_field_unique');
            $table->dropIndex('provider_contract_field_order_idx');

            $table->dropColumn('operation');

            $table->unique(['capability', 'field_key'], 'provider_contract_field_unique');
            $table->index(['capability', 'sort_order'], 'provider_contract_field_order_idx');
        });
    }
};
