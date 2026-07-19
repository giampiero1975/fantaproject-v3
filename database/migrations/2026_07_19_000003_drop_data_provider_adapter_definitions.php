<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('data_provider_adapter_definitions');
    }

    public function down(): void
    {
        // The adapter definition registry was removed: provider runtime is configured from DB HTTP mappings.
    }
};
