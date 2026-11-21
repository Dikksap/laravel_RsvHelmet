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
        Schema::create('stock_opname_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')
                ->constrained('stock_opnames')
                ->cascadeOnDelete();
            $table->foreignUuid('item_id')
                ->constrained('items')
                ->restrictOnDelete();
            $table->decimal('system_quantity', 15, 3)->default(0);
            $table->decimal('counted_quantity', 15, 3)->nullable();
            $table->decimal('adjustment_quantity', 15, 3)->nullable();
            $table->dateTime('counted_at')->nullable();
            $table->foreignId('counted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['stock_opname_id', 'item_id']);
            $table->index('item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opname_lines');
    }
};
