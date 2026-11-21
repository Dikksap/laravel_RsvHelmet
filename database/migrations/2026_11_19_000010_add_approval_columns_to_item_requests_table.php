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
        Schema::table('item_requests', function (Blueprint $table) {
            $table->foreignId('warehouse_id')
                ->nullable()
                ->after('status')
                ->constrained('warehouses')
                ->nullOnDelete();
            $table->foreignId('destination_warehouse_id')
                ->nullable()
                ->after('warehouse_id')
                ->constrained('warehouses')
                ->nullOnDelete();
            $table->foreignId('approved_by')
                ->nullable()
                ->after('destination_warehouse_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('approved_at')
                ->nullable()
                ->after('approved_by');
            $table->foreignId('rejected_by')
                ->nullable()
                ->after('approved_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('rejected_at')
                ->nullable()
                ->after('rejected_by');
            $table->text('rejection_note')
                ->nullable()
                ->after('rejected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropConstrainedForeignId('destination_warehouse_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn([
                'approved_at',
                'rejected_at',
                'rejection_note',
            ]);
        });
    }
};
