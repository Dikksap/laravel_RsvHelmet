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
        Schema::table('inventory_balances', function (Blueprint $table) {
            $table->dropColumn('quantity_reserved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_balances', function (Blueprint $table) {
            $table->decimal('quantity_reserved', 15, 3)->default(0)->after('quantity_on_hand');
        });
    }
};
