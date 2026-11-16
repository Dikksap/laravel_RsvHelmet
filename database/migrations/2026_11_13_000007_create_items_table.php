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
        Schema::create('items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Parent / variant configuration.
            $table->uuid('parent_item_id')->nullable();
            $table->boolean('has_variants')->default(false);

            // Identity.
            $table->string('item_code', 50)->unique();
            $table->string('barcode', 100)->nullable()->unique();
            $table->string('item_name');
            $table->text('description')->nullable();

            // Classification.
            $table->foreignId('item_type_id')
                ->constrained('item_types')
                ->restrictOnDelete();
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('item_categories')
                ->nullOnDelete();
            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();
            $table->foreignId('base_uom_id')
                ->constrained('uoms')
                ->restrictOnDelete();

            // Dimensions & weight.
            $table->decimal('weight', 10, 3)->default(0);
            $table->foreignId('weight_uom_id')
                ->nullable()
                ->constrained('uoms')
                ->nullOnDelete();
            $table->decimal('length', 10, 3)->default(0);
            $table->decimal('width', 10, 3)->default(0);
            $table->decimal('height', 10, 3)->default(0);
            $table->foreignId('dimension_uom_id')
                ->nullable()
                ->constrained('uoms')
                ->nullOnDelete();

            // Pricing.
            $table->decimal('base_price', 15, 2)->default(0);
            $table->decimal('standard_cost', 15, 2)->default(0);

            // Behaviour.
            $table->boolean('is_stockable')->default(true);
            $table->boolean('is_sellable')->default(true);
            $table->boolean('is_purchasable')->default(true);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('parent_item_id')
                ->references('id')
                ->on('items')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
