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
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnDelete();
            $table->foreignUuid('item_id')
                ->constrained('items')
                ->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 15, 3)->default(0);
            $table->decimal('quantity_reserved', 15, 3)->default(0);
            $table->decimal('minimum_quantity', 15, 3)->default(0);
            $table->timestamp('last_counted_at')->nullable();
            $table->timestamps();

            $table->unique(['warehouse_id', 'item_id']);
            $table->index('item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_balances');
    }
};
