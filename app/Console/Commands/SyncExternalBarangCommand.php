<?php

namespace App\Console\Commands;

use App\Models\Warehouse;
use App\Services\ExternalBarangSyncService;
use Illuminate\Console\Command;

class SyncExternalBarangCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:external-barang
        {--warehouse= : ID gudang tujuan penambahan stok}
        {--limit=100 : Jumlah record per request (maks 100)}
        {--all-pages : Tarik seluruh halaman paginasi}
        {--status= : Filter status tertentu dari API eksternal}
        {--dry-run : Jalankan simulasi pengecekan tanpa menambah stok}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi data unit helm finish good dari API publik eksternal ke stok gudang';

    /**
     * Execute the console command.
     */
    public function handle(ExternalBarangSyncService $syncService): int
    {
        $warehouseId = $this->option('warehouse') ?? config('services.external_barang.default_warehouse_id');

        $warehouse = filled($warehouseId)
            ? Warehouse::query()->where('is_active', true)->find($warehouseId)
            : Warehouse::query()->where('is_active', true)->orderBy('name')->first();

        if (! $warehouse) {
            $this->error('Gudang aktif tidak ditemukan. Pastikan ada gudang aktif atau gunakan opsi --warehouse=ID.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info(sprintf(
            'Memulai sinkronisasi unit barang eksternal ke gudang [%s]%s...',
            $warehouse->name,
            $dryRun ? ' (MODE SIMULASI)' : '',
        ));

        $result = $syncService->sync($warehouse, [
            'limit' => (int) $this->option('limit'),
            'all_pages' => (bool) $this->option('all-pages'),
            'dry_run' => $dryRun,
            'status' => $this->option('status'),
        ]);

        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Total Unit Diproses dari API', $result['total_processed']],
                ['Unit Berhasil Masuk Stok', $result['synced_count']],
                ['Unit Status Register (Diabaikan)', $result['skipped_register_count']],
                ['Unit Duplikat (Dilewati)', $result['skipped_duplicate_count']],
                ['Unit Belum Dimapping (Dilewati)', $result['unmapped_count']],
            ],
        );

        if (! empty($result['unmapped_variants'])) {
            $this->warn('Daftar varian eksternal yang belum dimapping ke barang RSV:');
            $rows = array_map(fn (array $v): array => [
                $v['external_variant_id'],
                $v['external_variant_code'] ?? '-',
                $v['external_product_name'] ?? '-',
                $v['external_color_name'] ?? '-',
                $v['external_size_name'] ?? '-',
            ], $result['unmapped_variants']);

            $this->table(['ID Variant', 'Kode Variant', 'Produk', 'Warna', 'Ukuran'], $rows);
        }

        $this->info($result['message']);

        return self::SUCCESS;
    }
}
