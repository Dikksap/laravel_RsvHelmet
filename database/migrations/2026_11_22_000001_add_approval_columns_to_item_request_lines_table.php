<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('item_request_lines', function (Blueprint $table) {
            $table->string('approval_status', 20)
                ->default('pending')
                ->after('quantity');
            $table->decimal('approved_quantity', 15, 3)
                ->nullable()
                ->after('approval_status');
            $table->string('rejection_note', 255)
                ->nullable()
                ->after('approved_quantity');

            $table->index('approval_status');
        });

        DB::table('item_request_lines')
            ->join('item_requests', 'item_requests.id', '=', 'item_request_lines.item_request_id')
            ->where('item_requests.status', 'completed')
            ->update([
                'item_request_lines.approval_status' => 'approved',
                'item_request_lines.approved_quantity' => DB::raw('item_request_lines.quantity'),
            ]);

        DB::table('item_request_lines')
            ->join('item_requests', 'item_requests.id', '=', 'item_request_lines.item_request_id')
            ->where('item_requests.status', 'rejected')
            ->update([
                'item_request_lines.approval_status' => 'rejected',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_request_lines', function (Blueprint $table) {
            $table->dropIndex(['approval_status']);
            $table->dropColumn([
                'approval_status',
                'approved_quantity',
                'rejection_note',
            ]);
        });
    }
};
