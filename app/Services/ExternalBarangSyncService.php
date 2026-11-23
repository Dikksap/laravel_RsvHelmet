<?php

namespace App\Services;

use App\Models\ExternalItemMapping;
use App\Models\ExternalSyncedBarang;
use App\Models\InventoryMovement;
use App\Models\Warehouse;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExternalBarangSyncService
{
    public function __construct(
        protected ExternalBarangApiService $apiService,
        protected StockMovementService $stockMovementService,
    ) {}

    /**
     * Jalankan proses sinkronisasi stok unit helm dari API eksternal.
     *
     * @param  Warehouse  $warehouse  Gudang tujuan penambahan stok
     * @param  array<string, mixed>  $options
     * @return array{
     *     success: bool,
     *     warehouse_id: int,
     *     warehouse_name: string,
     *     synced_count: int,
     *     skipped_register_count: int,
     *     skipped_duplicate_count: int,
     *     unmapped_count: int,
     *     unmapped_variants: array<int, array<string, mixed>>,
     *     total_processed: int,
     *     dry_run: bool,
     *     message: string
     * }
     */
    public function sync(Warehouse $warehouse, array $options = []): array
    {
        $excludedStatuses = array_map(
            'strtoupper',
            $options['excluded_statuses'] ?? config('services.external_barang.excluded_statuses', ['REGISTER']),
        );

        $limit = min((int) ($options['limit'] ?? 100), 100);
        $page = max((int) ($options['page'] ?? 1), 1);
        $allPages = (bool) ($options['all_pages'] ?? false);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $userId = $options['user_id'] ?? auth()->id();
        $statusFilter = $options['status'] ?? null;
        $variantIdFilter = $options['variant_id'] ?? null;

        $mappings = ExternalItemMapping::query()
            ->where('is_active', true)
            ->with('item')
            ->get()
            ->keyBy('external_variant_id');

        $syncedCount = 0;
        $skippedRegisterCount = 0;
        $skippedDuplicateCount = 0;
        $unmappedCount = 0;
        $unmappedVariants = [];
        $totalProcessed = 0;

        $currentPage = $page;

        do {
            $queryParams = [
                'page' => $currentPage,
                'limit' => $limit,
            ];

            if (filled($statusFilter)) {
                $queryParams['status'] = $statusFilter;
            }

            if (filled($variantIdFilter)) {
                $queryParams['variantId'] = $variantIdFilter;
            }

            $response = $this->apiService->getBarangList($queryParams);
            $units = $response['data'] ?? [];
            $meta = $response['meta'] ?? [];
            $totalPages = (int) ($meta['totalPages'] ?? 1);

            if (empty($units)) {
                break;
            }

            foreach ($units as $unit) {
                $totalProcessed++;
                $kodeBarang = trim((string) ($unit['kodeBarang'] ?? ''));

                if ($kodeBarang === '') {
                    continue;
                }

                $status = strtoupper(trim((string) ($unit['status'] ?? '')));

                // 1. Cek status: jika REGISTER (atau excluded), unit belum finish good -> lewati
                if (in_array($status, $excludedStatuses, true)) {
                    $skippedRegisterCount++;

                    continue;
                }

                // 2. Cek idempotensi: jika kodeBarang sudah pernah disinkronkan -> lewati
                $alreadySynced = ExternalSyncedBarang::query()
                    ->where('kode_barang', $kodeBarang)
                    ->exists();

                if ($alreadySynced) {
                    $skippedDuplicateCount++;

                    continue;
                }

                // 3. Cek mapping varian
                $variant = $unit['variant'] ?? [];
                $variantId = (int) ($variant['id'] ?? $unit['variantId'] ?? 0);
                $mapping = $mappings->get($variantId);

                if (! $mapping || ! $mapping->item) {
                    $unmappedCount++;
                    if ($variantId > 0 && ! isset($unmappedVariants[$variantId])) {
                        $unmappedVariants[$variantId] = [
                            'external_variant_id' => $variantId,
                            'external_variant_code' => $variant['kodeVariant'] ?? null,
                            'external_product_name' => is_array($variant['product'] ?? null) ? ($variant['product']['nama'] ?? null) : null,
                            'external_style_name' => is_array($variant['style'] ?? null) ? ($variant['style']['nama'] ?? null) : null,
                            'external_color_name' => is_array($variant['color'] ?? null) ? ($variant['color']['nama'] ?? null) : null,
                            'external_size_name' => is_array($variant['size'] ?? null) ? ($variant['size']['nama'] ?? null) : null,
                        ];
                    }

                    continue;
                }

                // 4. Proses unit baru jika bukan dry-run
                if ($dryRun) {
                    $syncedCount++;

                    continue;
                }

                $occurredAt = $this->resolveDate($unit);

                try {
                    DB::transaction(function () use (
                        $mapping,
                        $warehouse,
                        $kodeBarang,
                        $status,
                        $occurredAt,
                        $userId,
                        $unit,
                        $variantId,
                        &$syncedCount,
                    ): void {
                        $movement = $this->stockMovementService->record([
                            'type' => InventoryMovement::TYPE_IN_PRODUCTION,
                            'item_id' => $mapping->item_id,
                            'warehouse_id' => $warehouse->getKey(),
                            'quantity' => 1,
                            'reference' => $kodeBarang,
                            'note' => "Sinkronisasi otomatis produksi eksternal (Status: {$status})",
                            'occurred_at' => $occurredAt,
                            'created_by' => $userId,
                        ]);

                        ExternalSyncedBarang::create([
                            'kode_barang' => $kodeBarang,
                            'external_id' => $unit['id'] ?? null,
                            'external_variant_id' => $variantId,
                            'external_batch_id' => $unit['batchId'] ?? ($unit['batch']['id'] ?? null),
                            'item_id' => $mapping->item_id,
                            'warehouse_id' => $warehouse->getKey(),
                            'inventory_movement_id' => $movement->getKey(),
                            'status' => $status,
                            'external_occurred_at' => $occurredAt,
                            'synced_at' => now(),
                        ]);

                        $syncedCount++;
                    });
                } catch (Exception $e) {
                    Log::error('ExternalBarangSyncService: Gagal mencatat stok unit', [
                        'kodeBarang' => $kodeBarang,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $currentPage++;
        } while ($allPages && $currentPage <= $totalPages);

        $message = sprintf(
            'Sinkronisasi selesai: %d unit berhasil masuk stok ke %s, %d unit status register diabaikan, %d unit duplikat dilewati, %d unit belum dimapping.',
            $syncedCount,
            $warehouse->name,
            $skippedRegisterCount,
            $skippedDuplicateCount,
            $unmappedCount,
        );

        return [
            'success' => true,
            'warehouse_id' => $warehouse->getKey(),
            'warehouse_name' => $warehouse->name,
            'synced_count' => $syncedCount,
            'skipped_register_count' => $skippedRegisterCount,
            'skipped_duplicate_count' => $skippedDuplicateCount,
            'unmapped_count' => $unmappedCount,
            'unmapped_variants' => array_values($unmappedVariants),
            'total_processed' => $totalProcessed,
            'dry_run' => $dryRun,
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $unit
     */
    protected function resolveDate(array $unit): Carbon
    {
        $raw = $unit['finishGoodAt'] ?? $unit['tanggal'] ?? $unit['createdAt'] ?? null;

        if (filled($raw)) {
            try {
                return Carbon::parse($raw);
            } catch (Exception) {
                // fall through to now()
            }
        }

        return now();
    }
}
