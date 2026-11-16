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
        Schema::create('production_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code', 50)->unique();
            $table->string('status', 20)->default('draft');
            $table->foreignId('material_warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->nullOnDelete();
            $table->foreignId('output_warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->nullOnDelete();
            $table->date('started_at')->nullable();
            $table->date('finished_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_batches');
    }
};
