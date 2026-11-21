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
        Schema::table('item_request_lines', function (Blueprint $table) {
            $table->uuid('item_id')->nullable()->change();
            $table->string('requested_item_name', 255)->nullable()->after('item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_request_lines', function (Blueprint $table) {
            $table->dropColumn('requested_item_name');
        });

        Schema::table('item_request_lines', function (Blueprint $table) {
            $table->uuid('item_id')->nullable(false)->change();
        });
    }
};
