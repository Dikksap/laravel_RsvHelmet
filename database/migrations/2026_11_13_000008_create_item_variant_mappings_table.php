<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('item_variant_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('item_id')
                ->constrained('items')
                ->cascadeOnDelete();
            $table->foreignId('attribute_id')
                ->constrained('item_attributes')
                ->restrictOnDelete();
            $table->foreignId('attribute_value_id')
                ->constrained('item_attribute_values')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(['item_id', 'attribute_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_variant_mappings');
    }
};
