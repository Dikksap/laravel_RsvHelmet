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
        Schema::create('item_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_request_id')
                ->constrained('item_requests')
                ->cascadeOnDelete();
            $table->foreignUuid('item_id')
                ->constrained('items')
                ->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['item_request_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_request_lines');
    }
};
