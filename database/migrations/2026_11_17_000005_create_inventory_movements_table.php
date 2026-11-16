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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->foreignUuid('item_id')
                ->constrained('items')
                ->restrictOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->foreignId('production_batch_id')
                ->nullable()
                ->constrained('production_batches')
                ->nullOnDelete();
            $table->uuid('transfer_group_id')->nullable();
            $table->dateTime('occurred_at');
            $table->string('reference', 100)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['item_id', 'warehouse_id', 'occurred_at']);
            $table->index('production_batch_id');
            $table->index('transfer_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
