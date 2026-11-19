<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_components', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            $table->index(['parent_item_id', 'sort_order']);
        });

        DB::table('item_components')
            ->orderBy('parent_item_id')
            ->orderBy('id')
            ->get(['id', 'parent_item_id'])
            ->groupBy('parent_item_id')
            ->each(function ($rows): void {
                foreach ($rows->values() as $position => $row) {
                    DB::table('item_components')
                        ->where('id', $row->id)
                        ->update(['sort_order' => $position]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('item_components', function (Blueprint $table) {
            $table->dropIndex(['parent_item_id', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
