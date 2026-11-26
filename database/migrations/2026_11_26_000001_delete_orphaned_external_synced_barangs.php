<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Baris dengan inventory_movement_id NULL adalah sisa sinkronisasi yang
        // transaksinya sudah dihapus (stok sudah dikembalikan). Hapus agar kode
        // barang unik bisa ditarik ulang dan riwayat tidak menyesatkan.
        DB::table('external_synced_barangs')
            ->whereNull('inventory_movement_id')
            ->delete();
    }

    public function down(): void
    {
        // Pembersihan data, tidak dapat dikembalikan.
    }
};
