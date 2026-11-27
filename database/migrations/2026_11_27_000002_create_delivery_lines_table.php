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
        Schema::create('delivery_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')
                ->constrained('deliveries')
                ->cascadeOnDelete();
            $table->foreignUuid('item_id')
                ->constrained('items')
                ->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['delivery_id', 'item_id']);
            $table->index('item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_lines');
    }
};
