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
        Schema::create('external_item_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('item_id')
                ->constrained('items')
                ->cascadeOnDelete();
            $table->unsignedInteger('external_variant_id')->unique();
            $table->string('external_variant_code', 50)->nullable()->index();
            $table->string('external_product_name')->nullable();
            $table->string('external_style_name')->nullable();
            $table->string('external_color_name')->nullable();
            $table->string('external_size_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_item_mappings');
    }
};
