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
        Schema::table('external_synced_barangs', function (Blueprint $table) {
            $table->foreignId('production_batch_id')
                ->nullable()
                ->after('warehouse_id')
                ->constrained('production_batches')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('external_synced_barangs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_batch_id');
        });
    }
};
