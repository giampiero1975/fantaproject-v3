<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_provider_contract_fields', function (Blueprint $table) {
            $table->id();
            $table->string('capability', 50);
            $table->string('field_key', 100);
            $table->string('label', 150);
            $table->text('description')->nullable();
            $table->string('data_type', 50)->default('string');
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['capability', 'field_key'], 'provider_contract_field_unique');
            $table->index(['capability', 'sort_order'], 'provider_contract_field_order_idx');
        });

        $now = now();

        DB::table('data_provider_contract_fields')->insert([
            [
                'capability' => 'competitions',
                'field_key' => 'external_id',
                'label' => 'ID esterno principale',
                'description' => 'Identificatore stabile del provider da usare come chiave operativa della competizione.',
                'data_type' => 'string',
                'is_required' => true,
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'capability' => 'competitions',
                'field_key' => 'name',
                'label' => 'Nome competizione',
                'description' => 'Nome leggibile della competizione restituito dal provider.',
                'data_type' => 'string',
                'is_required' => true,
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'capability' => 'competitions',
                'field_key' => 'country',
                'label' => 'Paese',
                'description' => 'Nazione o area geografica della competizione.',
                'data_type' => 'string',
                'is_required' => true,
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'capability' => 'competitions',
                'field_key' => 'provider_numeric_id',
                'label' => 'ID numerico provider',
                'description' => 'Identificatore numerico del provider, utile per audit, diagnostica e chiamate future.',
                'data_type' => 'integer',
                'is_required' => false,
                'sort_order' => 40,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'capability' => 'competitions',
                'field_key' => 'country_code',
                'label' => 'Codice paese',
                'description' => 'Codice paese o area restituito dal provider.',
                'data_type' => 'string',
                'is_required' => false,
                'sort_order' => 50,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'capability' => 'competitions',
                'field_key' => 'type',
                'label' => 'Tipo competizione',
                'description' => 'Tipo competizione, ad esempio LEAGUE o CUP.',
                'data_type' => 'string',
                'is_required' => false,
                'sort_order' => 60,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'capability' => 'competitions',
                'field_key' => 'logo_url',
                'label' => 'Logo',
                'description' => 'URL logo o emblema della competizione.',
                'data_type' => 'url',
                'is_required' => false,
                'sort_order' => 70,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('data_provider_contract_fields');
    }
};
