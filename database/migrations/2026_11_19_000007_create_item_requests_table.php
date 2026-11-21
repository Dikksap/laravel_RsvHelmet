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
        Schema::create('item_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->nullable()->unique();
            $table->string('requester_name', 100);
            $table->foreignId('division_id')
                ->nullable()
                ->constrained('divisions')
                ->nullOnDelete();
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_requests');
    }
};
