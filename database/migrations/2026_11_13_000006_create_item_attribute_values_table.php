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
        Schema::create('item_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')
                ->constrained('item_attributes')
                ->cascadeOnDelete();
            $table->string('value', 50);
            $table->string('code', 10)->nullable();
            $table->timestamps();

            $table->unique(['attribute_id', 'value']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_attribute_values');
    }
};
