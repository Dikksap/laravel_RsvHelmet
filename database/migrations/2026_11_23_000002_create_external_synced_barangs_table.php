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
        Schema::create('external_synced_barangs', function (Blueprint $table) {
            $table->id();
            $table->string('kode_barang', 100)->unique();
            $table->unsignedBigInteger('external_id')->nullable();
            $table->unsignedInteger('external_variant_id')->index();
            $table->unsignedBigInteger('external_batch_id')->nullable();
            $table->foreignUuid('item_id')
                ->constrained('items')
                ->restrictOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();
            $table->foreignId('inventory_movement_id')
                ->nullable()
                ->constrained('inventory_movements')
                ->nullOnDelete();
            $table->string('status', 50);
            $table->dateTime('external_occurred_at')->nullable();
            $table->dateTime('synced_at');
            $table->timestamps();

            $table->index(['item_id', 'warehouse_id']);
            $table->index('synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_synced_barangs');
    }
};
