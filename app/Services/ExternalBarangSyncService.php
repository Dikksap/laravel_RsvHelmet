<?php

namespace App\Services;

use App\Models\ExternalItemMapping;
use App\Models\ExternalSyncedBarang;
use App\Models\InventoryMovement;
use App\Models\ProductionBatch;
use App\Models\Warehouse;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
        $excludedStatuses = $this->excludedStatuses($options);

        $limit = min((int) ($options['limit'] ?? 100), 100);
        $page = max((int) ($options['page'] ?? 1), 1);
        $allPages = (bool) ($options['all_pages'] ?? false);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $userId = $options['user_id'] ?? auth()->id();
        $statusFilter = $options['status'] ?? null;
        $variantIdFilter = $options['variant_id'] ?? null;
        $batchCodeFilter = filled($options['batch_code'] ?? null) ? trim((string) $options['batch_code']) : null;

        $productionBatch = filled($options['production_batch_id'] ?? null)
            ? $this->resolveProductionBatch($options['production_batch_id'])
            : null;

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

                // 1b. Cek kode batch eksternal bila operator memfilternya
                if ($batchCodeFilter !== null && ! $this->matchesBatchCode($batchCodeFilter, $unit, $kodeBarang)) {
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
                        $productionBatch,
                        &$syncedCount,
                    ): void {
                        $movement = $this->stockMovementService->record([
                            'type' => InventoryMovement::TYPE_IN_PRODUCTION,
                            'item_id' => $mapping->item_id,
                            'warehouse_id' => $warehouse->getKey(),
                            'quantity' => 1,
                            'production_batch_id' => $productionBatch?->getKey(),
                            'reference' => $kodeBarang,
                            'note' => "Sinkronisasi otomatis produksi eksternal (Status: {$status})",
                            'occurred_at' => $occurredAt,
                            'created_by' => $userId,
                        ]);

                        ExternalSyncedBarang::updateOrCreate(
                            ['kode_barang' => $kodeBarang],
                            [
                                'external_id' => $unit['id'] ?? null,
                                'external_variant_id' => $variantId,
                                'external_batch_id' => $unit['batchId'] ?? ($unit['batch']['id'] ?? null),
                                'item_id' => $mapping->item_id,
                                'warehouse_id' => $warehouse->getKey(),
                                'production_batch_id' => $productionBatch?->getKey(),
                                'inventory_movement_id' => $movement->getKey(),
                                'status' => $status,
                                'external_occurred_at' => $occurredAt,
                                'synced_at' => now(),
                            ],
                        );

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
     * Tarik seluruh unit baru satu varian eksternal menjadi satu transaksi
     * hasil produksi yang terhubung ke batch produksi terpilih.
     *
     * @param  array<string, mixed>  $options
     * @return array{synced_count: int, movement: InventoryMovement, variant: array<string, mixed>}
     */
    public function syncVariantOutput(Warehouse $warehouse, int $variantId, array $options = []): array
    {
        $mapping = $this->resolveMapping($variantId);
        $productionBatch = $this->resolveProductionBatch($options['production_batch_id'] ?? null);

        $status = filled($options['status'] ?? null)
            ? strtoupper(trim((string) $options['status']))
            : 'FINISHGOOD';

        $occurredAt = filled($options['occurred_at'] ?? null)
            ? Carbon::parse($options['occurred_at'])
            : now();

        $userId = $options['user_id'] ?? auth()->id();

        $units = $this->eligibleUnits(
            $variantId,
            $status,
            $options['tanggal_awal'] ?? null,
            $options['tanggal_akhir'] ?? null,
        );

        $variantCode = $mapping->external_variant_code ?? (string) $variantId;

        if ($units === []) {
            throw ValidationException::withMessages([
                'external_variant_id' => "Tidak ada unit baru berstatus {$status} untuk varian {$variantCode} pada periode tersebut.",
            ]);
        }

        $requestedQuantity = filled($options['quantity'] ?? null) ? (int) $options['quantity'] : null;

        if ($requestedQuantity !== null) {
            $units = array_slice($units, 0, max(0, $requestedQuantity));
        }

        if ($units === []) {
            throw ValidationException::withMessages([
                'external_quantity' => "Jumlah unit untuk varian {$variantCode} harus lebih dari 0.",
            ]);
        }

        $quantity = count($units);

        $movement = DB::transaction(function () use (
            $mapping,
            $warehouse,
            $productionBatch,
            $units,
            $status,
            $occurredAt,
            $userId,
            $variantId,
            $variantCode,
            $quantity,
        ): InventoryMovement {
            $movement = $this->stockMovementService->record([
                'type' => InventoryMovement::TYPE_IN_PRODUCTION,
                'item_id' => $mapping->item_id,
                'warehouse_id' => $warehouse->getKey(),
                'quantity' => $quantity,
                'production_batch_id' => $productionBatch->getKey(),
                'reference' => "{$productionBatch->batch_code} / {$variantCode}",
                'note' => "Hasil produksi dari API eksternal (Status: {$status}, {$quantity} unit)",
                'occurred_at' => $occurredAt,
                'created_by' => $userId,
            ]);

            foreach ($units as $unit) {
                ExternalSyncedBarang::updateOrCreate(
                    ['kode_barang' => $unit['kode_barang']],
                    [
                        'external_id' => $unit['external_id'],
                        'external_variant_id' => $variantId,
                        'external_batch_id' => $unit['external_batch_id'],
                        'item_id' => $mapping->item_id,
                        'warehouse_id' => $warehouse->getKey(),
                        'production_batch_id' => $productionBatch->getKey(),
                        'inventory_movement_id' => $movement->getKey(),
                        'status' => $unit['status'],
                        'external_occurred_at' => $unit['occurred_at'],
                        'synced_at' => now(),
                    ],
                );
            }

            return $movement;
        });

        return [
            'synced_count' => $quantity,
            'movement' => $movement,
            'variant' => [
                'external_variant_id' => $variantId,
                'external_variant_code' => $mapping->external_variant_code,
                'item_id' => $mapping->item_id,
            ],
        ];
    }

    /**
     * Tarik seluruh unit baru dari satu kode batch eksternal, lalu catat
     * satu transaksi hasil produksi untuk setiap barang RSV yang terpetakan.
     *
     * @param  array<string, mixed>  $options
     * @return array{
     *     synced_count: int,
     *     movement: InventoryMovement,
     *     movements: array<int, InventoryMovement>,
     *     groups: array<int, array<string, mixed>>,
     *     unmapped: array<int, array<string, mixed>>
     * }
     */
    public function syncBatchOutput(Warehouse $warehouse, string $batchCode, array $options = []): array
    {
        $batchCode = trim($batchCode);

        if ($batchCode === '') {
            throw ValidationException::withMessages([
                'external_batch_code' => 'Kode batch eksternal wajib diisi.',
            ]);
        }

        $productionBatch = $this->resolveProductionBatch($options['production_batch_id'] ?? null);

        $status = filled($options['status'] ?? null)
            ? strtoupper(trim((string) $options['status']))
            : 'FINISHGOOD';

        $occurredAt = filled($options['occurred_at'] ?? null)
            ? Carbon::parse($options['occurred_at'])
            : now();

        $userId = $options['user_id'] ?? auth()->id();

        $grouped = $this->groupBatchUnits(
            $this->collectUnits(null, $status, $options['tanggal_awal'] ?? null, $options['tanggal_akhir'] ?? null, $batchCode),
        );

        if ($grouped['groups'] === []) {
            $message = $grouped['unmapped'] === []
                ? "Tidak ada unit baru berstatus {$status} untuk kode batch {$batchCode} pada periode tersebut."
                : "Semua varian pada kode batch {$batchCode} belum dipetakan ke barang RSV.";

            throw ValidationException::withMessages([
                'external_batch_code' => $message,
            ]);
        }

        $requestedQuantities = collect($options['quantities'] ?? [])
            ->mapWithKeys(fn (mixed $quantity, mixed $variantId): array => [(int) $variantId => max(0, (int) $quantity)])
            ->all();

        $groups = collect($grouped['groups'])
            ->map(function (array $group) use ($requestedQuantities): array {
                $eligible = count($group['units']);
                $quantity = array_key_exists($group['external_variant_id'], $requestedQuantities)
                    ? min($eligible, $requestedQuantities[$group['external_variant_id']])
                    : $eligible;

                $group['quantity'] = $quantity;
                $group['units'] = array_slice($group['units'], 0, $quantity);

                return $group;
            })
            ->filter(fn (array $group): bool => $group['quantity'] > 0)
            ->values()
            ->all();

        if ($groups === []) {
            throw ValidationException::withMessages([
                'external_quantities' => "Jumlah unit yang diminta untuk kode batch {$batchCode} seluruhnya 0.",
            ]);
        }

        $movements = DB::transaction(function () use (
            $groups,
            $warehouse,
            $productionBatch,
            $status,
            $batchCode,
            $occurredAt,
            $userId,
        ): array {
            $movements = [];

            foreach ($groups as $group) {
                $quantity = $group['quantity'];

                $movement = $this->stockMovementService->record([
                    'type' => InventoryMovement::TYPE_IN_PRODUCTION,
                    'item_id' => $group['item_id'],
                    'warehouse_id' => $warehouse->getKey(),
                    'quantity' => $quantity,
                    'production_batch_id' => $productionBatch->getKey(),
                    'reference' => "{$productionBatch->batch_code} / {$group['external_variant_code']}",
                    'note' => "Hasil produksi dari API eksternal (Batch: {$batchCode}, Status: {$status}, {$quantity} unit)",
                    'occurred_at' => $occurredAt,
                    'created_by' => $userId,
                ]);

                foreach ($group['units'] as $unit) {
                    ExternalSyncedBarang::updateOrCreate(
                        ['kode_barang' => $unit['kode_barang']],
                        [
                            'external_id' => $unit['external_id'],
                            'external_variant_id' => $group['external_variant_id'],
                            'external_batch_id' => $unit['external_batch_id'],
                            'item_id' => $group['item_id'],
                            'warehouse_id' => $warehouse->getKey(),
                            'production_batch_id' => $productionBatch->getKey(),
                            'inventory_movement_id' => $movement->getKey(),
                            'status' => $unit['status'],
                            'external_occurred_at' => $unit['occurred_at'],
                            'synced_at' => now(),
                        ],
                    );
                }

                $movements[] = $movement;
            }

            return $movements;
        });

        return [
            'synced_count' => (int) collect($movements)->sum(fn (InventoryMovement $movement): float => (float) $movement->quantity),
            'movement' => $movements[0],
            'movements' => $movements,
            'groups' => $groups,
            'unmapped' => $grouped['unmapped'],
        ];
    }

    /**
     * Ringkasan unit baru dari satu kode batch eksternal untuk pratinjau form.
     *
     * @return array{total_units: int, groups: array<int, array<string, mixed>>, unmapped: array<int, array<string, mixed>>}
     */
    public function previewBatchOutput(
        string $batchCode,
        ?string $tanggalAwal = null,
        ?string $tanggalAkhir = null,
        ?string $status = 'FINISHGOOD',
    ): array {
        $batchCode = trim($batchCode);

        if ($batchCode === '') {
            return ['total_units' => 0, 'groups' => [], 'unmapped' => []];
        }

        $grouped = $this->groupBatchUnits(
            $this->collectUnits(null, $status, $tanggalAwal, $tanggalAkhir, $batchCode),
        );

        return [
            'total_units' => (int) collect($grouped['groups'])
                ->sum(fn (array $group): int => count($group['units'])),
            'groups' => $grouped['groups'],
            'unmapped' => $grouped['unmapped'],
        ];
    }

    /**
     * Hitung jumlah unit baru yang siap dicatat sebagai hasil produksi.
     */
    public function countEligibleUnits(
        int $variantId,
        ?string $tanggalAwal = null,
        ?string $tanggalAkhir = null,
        ?string $status = 'FINISHGOOD',
    ): int {
        return count($this->eligibleUnits($variantId, $status, $tanggalAwal, $tanggalAkhir));
    }

    /**
     * Kelompokkan unit per varian, sekaligus memetakan ke barang RSV.
     *
     * @param  array<int, array<string, mixed>>  $units
     * @return array{groups: array<int, array<string, mixed>>, unmapped: array<int, array<string, mixed>>}
     */
    protected function groupBatchUnits(array $units): array
    {
        if ($units === []) {
            return ['groups' => [], 'unmapped' => []];
        }

        $byVariant = collect($units)->groupBy('external_variant_id');

        $mappings = ExternalItemMapping::query()
            ->where('is_active', true)
            ->whereIn('external_variant_id', $byVariant->keys()->all())
            ->with('item')
            ->get()
            ->keyBy('external_variant_id');

        $groups = [];
        $unmapped = [];

        foreach ($byVariant as $variantId => $variantUnits) {
            $mapping = $mappings->get($variantId);

            if (! $mapping || ! $mapping->item) {
                $unmapped[] = [
                    'external_variant_id' => (int) $variantId,
                    'external_variant_code' => $variantUnits->first()['external_variant_code'] ?? null,
                    'unit_count' => $variantUnits->count(),
                ];

                continue;
            }

            $groups[] = [
                'external_variant_id' => (int) $variantId,
                'external_variant_code' => $mapping->external_variant_code ?? (string) $variantId,
                'item_id' => $mapping->item_id,
                'item_code' => $mapping->item->item_code,
                'item_name' => $mapping->item->item_name,
                'units' => $variantUnits->values()->all(),
            ];
        }

        return ['groups' => $groups, 'unmapped' => $unmapped];
    }

    /**
     * Kumpulkan unit API yang belum pernah disinkronkan sesuai filter.
     *
     * @return array<int, array{
     *     kode_barang: string,
     *     external_variant_id: int,
     *     external_variant_code: mixed,
     *     external_id: mixed,
     *     external_batch_id: mixed,
     *     status: string,
     *     occurred_at: Carbon
     * }>
     */
    protected function eligibleUnits(
        int $variantId,
        ?string $status,
        ?string $tanggalAwal,
        ?string $tanggalAkhir,
    ): array {
        return $this->collectUnits($variantId, $status, $tanggalAwal, $tanggalAkhir);
    }

    /**
     * Ambil unit dari API lalu terapkan filter status, tanggal, kode batch,
     * dan idempotensi kode barang.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function collectUnits(
        ?int $variantId,
        ?string $status,
        ?string $tanggalAwal,
        ?string $tanggalAkhir,
        ?string $batchCode = null,
    ): array {
        if ($variantId !== null && $variantId <= 0) {
            return [];
        }

        $excludedStatuses = $this->excludedStatuses();
        $status = filled($status) ? strtoupper(trim((string) $status)) : null;
        $awal = filled($tanggalAwal) ? Carbon::parse($tanggalAwal)->startOfDay() : null;
        $akhir = filled($tanggalAkhir) ? Carbon::parse($tanggalAkhir)->endOfDay() : null;
        $batchCode = filled($batchCode) ? trim((string) $batchCode) : null;

        $units = [];
        $page = 1;

        do {
            $params = [
                'page' => $page,
                'limit' => 100,
            ];

            if ($variantId !== null) {
                $params['variantId'] = $variantId;
            }

            if (filled($status)) {
                $params['status'] = $status;
            }

            $response = $this->apiService->getBarangList($params);
            $rows = $response['data'] ?? [];
            $totalPages = (int) ($response['meta']['totalPages'] ?? 1);

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $unit) {
                $kodeBarang = trim((string) ($unit['kodeBarang'] ?? ''));

                if ($kodeBarang === '' || isset($units[$kodeBarang])) {
                    continue;
                }

                $unitStatus = strtoupper(trim((string) ($unit['status'] ?? '')));

                if (in_array($unitStatus, $excludedStatuses, true)) {
                    continue;
                }

                if (filled($status) && $unitStatus !== $status) {
                    continue;
                }

                $variantCode = $unit['variant']['kodeVariant'] ?? null;

                if ($batchCode !== null && ! $this->matchesBatchCode($batchCode, $unit, $kodeBarang)) {
                    continue;
                }

                $occurredAt = $this->resolveDate($unit);

                if ($awal && $occurredAt->lt($awal)) {
                    continue;
                }

                if ($akhir && $occurredAt->gt($akhir)) {
                    continue;
                }

                $units[$kodeBarang] = [
                    'kode_barang' => $kodeBarang,
                    'external_variant_id' => (int) ($unit['variant']['id'] ?? $unit['variantId'] ?? 0),
                    'external_variant_code' => $variantCode ?? ($unit['kodeVariant'] ?? null),
                    'external_id' => $unit['id'] ?? null,
                    'external_batch_id' => $unit['batchId'] ?? ($unit['batch']['id'] ?? null),
                    'status' => $unitStatus,
                    'occurred_at' => $occurredAt,
                ];
            }

            $page++;
        } while ($page <= $totalPages);

        if ($units === []) {
            return [];
        }

        $syncedCodes = ExternalSyncedBarang::query()
            ->whereIn('kode_barang', array_keys($units))
            ->whereNotNull('inventory_movement_id')
            ->pluck('kode_barang')
            ->flip()
            ->all();

        return array_values(array_filter(
            $units,
            fn (array $unit): bool => ! isset($syncedCodes[$unit['kode_barang']]),
        ));
    }

    /**
     * Cocokkan kode batch yang diisi operator dengan data batch pada unit API.
     *
     * @param  array<string, mixed>  $unit
     */
    protected function matchesBatchCode(string $batchCode, array $unit, string $kodeBarang): bool
    {
        $needle = strtolower(trim($batchCode));

        if ($needle === '') {
            return true;
        }

        $candidates = array_filter([
            $unit['kodeBatch'] ?? null,
            $unit['batchCode'] ?? null,
            $unit['batch']['kode'] ?? null,
            $unit['batch']['kodeBatch'] ?? null,
            $unit['batchId'] ?? ($unit['batch']['id'] ?? null),
            explode('-', $kodeBarang)[0] ?? null,
        ], fn (mixed $value): bool => filled($value));

        foreach ($candidates as $candidate) {
            if (strtolower(trim((string) $candidate)) === $needle) {
                return true;
            }
        }

        return false;
    }

    protected function resolveMapping(int $variantId): ExternalItemMapping
    {
        $mapping = ExternalItemMapping::query()
            ->where('is_active', true)
            ->where('external_variant_id', $variantId)
            ->with('item')
            ->first();

        if (! $mapping || ! $mapping->item) {
            throw ValidationException::withMessages([
                'external_variant_id' => 'Varian eksternal belum dipetakan ke barang RSV.',
            ]);
        }

        return $mapping;
    }

    protected function resolveProductionBatch(mixed $batchId): ProductionBatch
    {
        if (blank($batchId)) {
            throw ValidationException::withMessages([
                'production_batch_id' => 'Batch produksi wajib dipilih.',
            ]);
        }

        $batch = ProductionBatch::query()->find($batchId);

        if (! $batch) {
            throw ValidationException::withMessages([
                'production_batch_id' => 'Batch produksi tidak ditemukan.',
            ]);
        }

        return $batch;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, string>
     */
    protected function excludedStatuses(array $options = []): array
    {
        return array_map(
            'strtoupper',
            $options['excluded_statuses'] ?? config('services.external_barang.excluded_statuses', ['REGISTER']),
        );
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
