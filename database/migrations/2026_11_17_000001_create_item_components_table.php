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
        Schema::create('item_components', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('parent_item_id')
                ->constrained('items')
                ->cascadeOnDelete();
            $table->foreignUuid('component_item_id')
                ->constrained('items')
                ->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->string('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['parent_item_id', 'component_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_components');
    }
};
