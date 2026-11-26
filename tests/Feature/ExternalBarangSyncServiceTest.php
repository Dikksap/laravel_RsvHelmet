<?php

namespace Tests\Feature;

use App\Models\ExternalItemMapping;
use App\Models\ExternalSyncedBarang;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\ProductionBatch;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ExternalBarangApiService;
use App\Services\ExternalBarangSyncService;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExternalBarangSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Warehouse $warehouse;

    protected Item $item;

    protected ExternalItemMapping $mapping;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->warehouse = Warehouse::factory()->create(['name' => 'Gudang Finish Good']);
        $this->item = Item::factory()->create(['item_code' => 'G00002', 'item_name' => 'Windbreaker Motif Carbon LG']);

        $this->mapping = ExternalItemMapping::create([
            'item_id' => $this->item->getKey(),
            'external_variant_id' => 5,
            'external_variant_code' => 'WB005',
            'external_product_name' => 'Windbreaker',
            'external_style_name' => 'Motif',
            'external_color_name' => 'Carbon',
            'external_size_name' => 'LG',
            'is_active' => true,
        ]);
    }

    public function test_sync_adds_stock_for_non_register_units(): void
    {
        Http::fake([
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 101,
                        'kodeBarang' => 'BC001-WB005-181126-0001',
                        'variantId' => 5,
                        'batchId' => 1,
                        'status' => 'FINISHGOOD',
                        'tanggal' => '2026-11-17T17:19:42.416Z',
                        'finishGoodAt' => '2026-11-17T17:20:00.000Z',
                        'variant' => [
                            'id' => 5,
                            'kodeVariant' => 'WB005',
                        ],
                    ],
                    [
                        'id' => 102,
                        'kodeBarang' => 'BC001-WB005-181126-0002',
                        'variantId' => 5,
                        'batchId' => 1,
                        'status' => 'OUT',
                        'tanggal' => '2026-11-17T17:21:42.416Z',
                        'finishGoodAt' => '2026-11-17T17:22:00.000Z',
                        'variant' => [
                            'id' => 5,
                            'kodeVariant' => 'WB005',
                        ],
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        $apiService = new ExternalBarangApiService('http://localhost:8000');
        $syncService = new ExternalBarangSyncService($apiService, app(StockMovementService::class));

        $result = $syncService->sync($this->warehouse);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['synced_count']);
        $this->assertSame(0, $result['skipped_register_count']);
        $this->assertSame(0, $result['skipped_duplicate_count']);

        // Pastikan saldo gudang bertambah 2
        $balance = InventoryBalance::query()
            ->where('item_id', $this->item->getKey())
            ->where('warehouse_id', $this->warehouse->getKey())
            ->first();

        $this->assertNotNull($balance);
        $this->assertEquals(2.0, (float) $balance->quantity_on_hand);

        // Pastikan kedua unit tercatat di external_synced_barangs
        $this->assertDatabaseHas('external_synced_barangs', [
            'kode_barang' => 'BC001-WB005-181126-0001',
            'item_id' => $this->item->getKey(),
            'warehouse_id' => $this->warehouse->getKey(),
            'status' => 'FINISHGOOD',
        ]);
        $this->assertDatabaseHas('external_synced_barangs', [
            'kode_barang' => 'BC001-WB005-181126-0002',
            'item_id' => $this->item->getKey(),
            'warehouse_id' => $this->warehouse->getKey(),
            'status' => 'OUT',
        ]);

        // Pastikan movement bertipe in_production
        $movement = InventoryMovement::query()
            ->where('reference', 'BC001-WB005-181126-0001')
            ->first();

        $this->assertNotNull($movement);
        $this->assertSame(InventoryMovement::TYPE_IN_PRODUCTION, $movement->type);
        $this->assertEquals(1.0, (float) $movement->quantity);
    }

    public function test_sync_ignores_register_status_units(): void
    {
        Http::fake([
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 103,
                        'kodeBarang' => 'BC001-WB005-181126-0003',
                        'variantId' => 5,
                        'status' => 'REGISTER',
                        'tanggal' => '2026-11-17T17:19:42.416Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        $apiService = new ExternalBarangApiService('http://localhost:8000');
        $syncService = new ExternalBarangSyncService($apiService, app(StockMovementService::class));

        $result = $syncService->sync($this->warehouse);

        $this->assertSame(0, $result['synced_count']);
        $this->assertSame(1, $result['skipped_register_count']);

        $this->assertDatabaseMissing('external_synced_barangs', [
            'kode_barang' => 'BC001-WB005-181126-0003',
        ]);
    }

    public function test_sync_is_idempotent_and_skips_already_synced_units(): void
    {
        Http::fake([
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 104,
                        'kodeBarang' => 'BC001-WB005-181126-0004',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'tanggal' => '2026-11-17T17:19:42.416Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        $apiService = new ExternalBarangApiService('http://localhost:8000');
        $syncService = new ExternalBarangSyncService($apiService, app(StockMovementService::class));

        // Putaran 1: unit disinkronkan
        $result1 = $syncService->sync($this->warehouse);
        $this->assertSame(1, $result1['synced_count']);
        $this->assertSame(0, $result1['skipped_duplicate_count']);

        // Putaran 2: unit yang sama ditarik lagi -> harus diskip
        $result2 = $syncService->sync($this->warehouse);
        $this->assertSame(0, $result2['synced_count']);
        $this->assertSame(1, $result2['skipped_duplicate_count']);

        // Saldo tetap 1, tidak menjadi 2
        $balance = InventoryBalance::query()
            ->where('item_id', $this->item->getKey())
            ->where('warehouse_id', $this->warehouse->getKey())
            ->first();

        $this->assertEquals(1.0, (float) $balance->quantity_on_hand);
    }

    public function test_sync_skips_unmapped_variants_without_failing(): void
    {
        Http::fake([
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 105,
                        'kodeBarang' => 'BC001-WB999-181126-0005',
                        'variantId' => 999, // Belum dimapping
                        'status' => 'FINISHGOOD',
                        'tanggal' => '2026-11-17T17:19:42.416Z',
                        'variant' => [
                            'id' => 999,
                            'kodeVariant' => 'WB999',
                            'product' => ['nama' => 'Unknown Helmet'],
                        ],
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        $apiService = new ExternalBarangApiService('http://localhost:8000');
        $syncService = new ExternalBarangSyncService($apiService, app(StockMovementService::class));

        $result = $syncService->sync($this->warehouse);

        $this->assertSame(0, $result['synced_count']);
        $this->assertSame(1, $result['unmapped_count']);
        $this->assertCount(1, $result['unmapped_variants']);
        $this->assertSame(999, $result['unmapped_variants'][0]['external_variant_id']);
    }

    public function test_sync_attaches_selected_production_batch(): void
    {
        $batch = ProductionBatch::factory()->create(['batch_code' => 'BATCH-EXT-02']);

        Http::fake([
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 301,
                        'kodeBarang' => 'BC001-WB005-241126-0301',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-20T10:00:00.000Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        $apiService = new ExternalBarangApiService('http://localhost:8000');
        $syncService = new ExternalBarangSyncService($apiService, app(StockMovementService::class));

        $result = $syncService->sync($this->warehouse, [
            'production_batch_id' => $batch->getKey(),
        ]);

        $this->assertSame(1, $result['synced_count']);

        $movement = InventoryMovement::query()->sole();
        $this->assertSame($batch->getKey(), $movement->production_batch_id);

        $this->assertDatabaseHas('external_synced_barangs', [
            'kode_barang' => 'BC001-WB005-241126-0301',
            'production_batch_id' => $batch->getKey(),
        ]);
    }

    public function test_sync_variant_output_aggregates_units_into_one_movement(): void
    {
        $batch = ProductionBatch::factory()->create(['batch_code' => 'BATCH-EXT-03']);

        Http::fake([
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 311,
                        'kodeBarang' => 'BC001-WB005-241126-0311',
                        'variantId' => 5,
                        'batchId' => 9,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-20T10:00:00.000Z',
                    ],
                    [
                        'id' => 312,
                        'kodeBarang' => 'BC001-WB005-241126-0312',
                        'variantId' => 5,
                        'batchId' => 9,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-21T10:00:00.000Z',
                    ],
                    [
                        'id' => 313,
                        'kodeBarang' => 'BC001-WB005-241126-0313',
                        'variantId' => 5,
                        'batchId' => 9,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-22T10:00:00.000Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        $apiService = new ExternalBarangApiService('http://localhost:8000');
        $syncService = new ExternalBarangSyncService($apiService, app(StockMovementService::class));

        $result = $syncService->syncVariantOutput($this->warehouse, 5, [
            'production_batch_id' => $batch->getKey(),
            'tanggal_awal' => '2026-11-01',
            'tanggal_akhir' => '2026-11-30',
            'status' => 'FINISHGOOD',
            'occurred_at' => '2026-11-24 08:00:00',
        ]);

        $this->assertSame(3, $result['synced_count']);

        $movement = InventoryMovement::query()->sole();
        $this->assertSame(InventoryMovement::TYPE_IN_PRODUCTION, $movement->type);
        $this->assertEquals(3.0, (float) $movement->quantity);
        $this->assertSame($batch->getKey(), $movement->production_batch_id);
        $this->assertSame('BATCH-EXT-03 / WB005', $movement->reference);

        $this->assertSame(3, ExternalSyncedBarang::query()
            ->where('inventory_movement_id', $movement->getKey())
            ->count());

        $this->assertDatabaseHas('external_synced_barangs', [
            'kode_barang' => 'BC001-WB005-241126-0311',
            'item_id' => $this->item->getKey(),
            'production_batch_id' => $batch->getKey(),
        ]);

        $this->assertSame(0, InventoryMovement::query()
            ->where('type', InventoryMovement::TYPE_OUT_PRODUCTION)
            ->count());

        $balance = InventoryBalance::query()
            ->where('item_id', $this->item->getKey())
            ->where('warehouse_id', $this->warehouse->getKey())
            ->sole();

        $this->assertEquals(3.0, (float) $balance->quantity_on_hand);
    }

    public function test_sync_variant_output_skips_duplicates_and_out_of_range_units(): void
    {
        $batch = ProductionBatch::factory()->create();

        $existingMovement = InventoryMovement::factory()->create([
            'item_id' => $this->item->getKey(),
            'warehouse_id' => $this->warehouse->getKey(),
        ]);

        ExternalSyncedBarang::factory()->create([
            'kode_barang' => 'BC001-WB005-241126-DUP',
            'external_variant_id' => 5,
            'item_id' => $this->item->getKey(),
            'warehouse_id' => $this->warehouse->getKey(),
            'inventory_movement_id' => $existingMovement->getKey(),
        ]);

        Http::fake([
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 321,
                        'kodeBarang' => 'BC001-WB005-241126-DUP',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-20T10:00:00.000Z',
                    ],
                    [
                        'id' => 322,
                        'kodeBarang' => 'BC001-WB005-241026-OLD',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-10-20T10:00:00.000Z',
                    ],
                    [
                        'id' => 323,
                        'kodeBarang' => 'BC001-WB005-241126-NEW',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-21T10:00:00.000Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        $apiService = new ExternalBarangApiService('http://localhost:8000');
        $syncService = new ExternalBarangSyncService($apiService, app(StockMovementService::class));

        $result = $syncService->syncVariantOutput($this->warehouse, 5, [
            'production_batch_id' => $batch->getKey(),
            'tanggal_awal' => '2026-11-01',
            'tanggal_akhir' => '2026-11-30',
        ]);

        $this->assertSame(1, $result['synced_count']);
        $this->assertDatabaseHas('external_synced_barangs', [
            'kode_barang' => 'BC001-WB005-241126-NEW',
        ]);
        $this->assertDatabaseMissing('external_synced_barangs', [
            'kode_barang' => 'BC001-WB005-241026-OLD',
        ]);

        $this->expectException(ValidationException::class);

        $syncService->syncVariantOutput($this->warehouse, 5, [
            'production_batch_id' => $batch->getKey(),
            'tanggal_awal' => '2026-11-01',
            'tanggal_akhir' => '2026-11-30',
        ]);
    }

    public function test_sync_variant_output_fails_when_variant_unmapped(): void
    {
        $batch = ProductionBatch::factory()->create();

        $apiService = new ExternalBarangApiService('http://localhost:8000');
        $syncService = new ExternalBarangSyncService($apiService, app(StockMovementService::class));

        $this->expectException(ValidationException::class);

        $syncService->syncVariantOutput($this->warehouse, 999, [
            'production_batch_id' => $batch->getKey(),
        ]);
    }

    public function test_sync_reclaims_units_whose_previous_movement_was_deleted(): void
    {
        $batch = ProductionBatch::factory()->create(['batch_code' => 'BATCH-RECLAIM-01']);

        ExternalSyncedBarang::factory()->create([
            'kode_barang' => 'BC001-WB005-241126-0701',
            'external_variant_id' => 5,
            'item_id' => $this->item->getKey(),
            'warehouse_id' => $this->warehouse->getKey(),
            'inventory_movement_id' => null,
            'status' => 'FINISHGOOD',
        ]);

        Http::fake([
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 701,
                        'kodeBarang' => 'BC001-WB005-241126-0701',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-22T10:00:00.000Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        $apiService = new ExternalBarangApiService('http://localhost:8000');
        $syncService = new ExternalBarangSyncService($apiService, app(StockMovementService::class));

        $result = $syncService->syncVariantOutput($this->warehouse, 5, [
            'production_batch_id' => $batch->getKey(),
        ]);

        $this->assertSame(1, $result['synced_count']);
        $this->assertSame(1, ExternalSyncedBarang::query()->count());

        $syncedRow = ExternalSyncedBarang::query()->sole();
        $this->assertSame($result['movement']->getKey(), $syncedRow->inventory_movement_id);
        $this->assertSame($batch->getKey(), $syncedRow->production_batch_id);
    }

    public function test_sync_reclaims_orphaned_units_without_live_movement(): void
    {
        ExternalSyncedBarang::factory()->create([
            'kode_barang' => 'BC001-WB005-181126-0007',
            'external_variant_id' => 5,
            'item_id' => $this->item->getKey(),
            'warehouse_id' => $this->warehouse->getKey(),
            'inventory_movement_id' => null,
            'status' => 'FINISHGOOD',
        ]);

        Http::fake([
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 107,
                        'kodeBarang' => 'BC001-WB005-181126-0007',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'tanggal' => '2026-11-17T17:19:42.416Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        $apiService = new ExternalBarangApiService('http://localhost:8000');
        $syncService = new ExternalBarangSyncService($apiService, app(StockMovementService::class));

        $result = $syncService->sync($this->warehouse);

        $this->assertSame(1, $result['synced_count']);
        $this->assertSame(0, $result['skipped_duplicate_count']);
        $this->assertSame(1, ExternalSyncedBarang::query()->count());

        $movement = InventoryMovement::query()->sole();
        $syncedRow = ExternalSyncedBarang::query()->sole();

        $this->assertSame($movement->getKey(), $syncedRow->inventory_movement_id);

        $balance = InventoryBalance::query()
            ->where('item_id', $this->item->getKey())
            ->where('warehouse_id', $this->warehouse->getKey())
            ->sole();

        $this->assertEquals(1.0, (float) $balance->quantity_on_hand);
    }

    public function test_sync_batch_output_respects_max_units_cap(): void
    {
        $batch = ProductionBatch::factory()->create(['batch_code' => 'BATCH-CAP-01']);

        $secondItem = Item::factory()->create([
            'item_code' => 'G00003',
            'item_name' => 'Windbreaker Motif Navy MD',
        ]);

        ExternalItemMapping::create([
            'item_id' => $secondItem->getKey(),
            'external_variant_id' => 6,
            'external_variant_code' => 'WB006',
            'is_active' => true,
        ]);

        Http::fake([
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 711,
                        'kodeBarang' => 'BC001-WB005-241126-0711',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-20T10:00:00.000Z',
                    ],
                    [
                        'id' => 712,
                        'kodeBarang' => 'BC001-WB005-241126-0712',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-20T11:00:00.000Z',
                    ],
                    [
                        'id' => 713,
                        'kodeBarang' => 'BC001-WB005-241126-0713',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-20T12:00:00.000Z',
                    ],
                    [
                        'id' => 714,
                        'kodeBarang' => 'BC001-WB006-241126-0714',
                        'variantId' => 6,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-21T10:00:00.000Z',
                    ],
                    [
                        'id' => 715,
                        'kodeBarang' => 'BC001-WB006-241126-0715',
                        'variantId' => 6,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-21T11:00:00.000Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        $apiService = new ExternalBarangApiService('http://localhost:8000');
        $syncService = new ExternalBarangSyncService($apiService, app(StockMovementService::class));

        $result = $syncService->syncBatchOutput($this->warehouse, 'BC001', [
            'production_batch_id' => $batch->getKey(),
            'max_units' => 3,
        ]);

        $this->assertSame(3, $result['synced_count']);
        $this->assertCount(1, $result['movements']);

        $movement = $result['movements'][0];
        $this->assertSame($this->item->getKey(), $movement->item_id);
        $this->assertEquals(3.0, (float) $movement->quantity);

        $this->assertSame(3, ExternalSyncedBarang::query()->count());
        $this->assertDatabaseMissing('external_synced_barangs', [
            'kode_barang' => 'BC001-WB006-241126-0714',
        ]);
        $this->assertDatabaseMissing('external_synced_barangs', [
            'kode_barang' => 'BC001-WB006-241126-0715',
        ]);
    }

    public function test_sync_batch_output_aggregates_all_variants_in_batch(): void
    {
        $batch = ProductionBatch::factory()->create(['batch_code' => 'BATCH-BC-01']);

        $secondItem = Item::factory()->create([
            'item_code' => 'G00003',
            'item_name' => 'Windbreaker Motif Navy MD',
        ]);

        ExternalItemMapping::create([
            'item_id' => $secondItem->getKey(),
            'external_variant_id' => 6,
            'external_variant_code' => 'WB006',
            'is_active' => true,
        ]);

        Http::fake([
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 501,
                        'kodeBarang' => 'BC001-WB005-241126-0501',
                        'variantId' => 5,
                        'batchId' => 9,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-20T10:00:00.000Z',
                    ],
                    [
                        'id' => 502,
                        'kodeBarang' => 'BC001-WB005-241126-0502',
                        'variantId' => 5,
                        'batchId' => 9,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-20T11:00:00.000Z',
                    ],
                    [
                        'id' => 503,
                        'kodeBarang' => 'BC001-WB006-241126-0503',
                        'variantId' => 6,
                        'batchId' => 9,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-21T10:00:00.000Z',
                    ],
                    [
                        'id' => 504,
                        'kodeBarang' => 'BC002-WB005-241126-0504',
                        'variantId' => 5,
                        'batchId' => 10,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-21T12:00:00.000Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        $apiService = new ExternalBarangApiService('http://localhost:8000');
        $syncService = new ExternalBarangSyncService($apiService, app(StockMovementService::class));

        $preview = $syncService->previewBatchOutput('BC001', null, null, 'FINISHGOOD');

        $this->assertSame(3, $preview['total_units']);
        $this->assertCount(2, $preview['groups']);

        $result = $syncService->syncBatchOutput($this->warehouse, 'BC001', [
            'production_batch_id' => $batch->getKey(),
            'status' => 'FINISHGOOD',
            'occurred_at' => '2026-11-24 08:00:00',
        ]);

        $this->assertSame(3, $result['synced_count']);
        $this->assertCount(2, $result['movements']);
        $this->assertSame([], $result['unmapped']);

        $productMovement = InventoryMovement::query()
            ->where('item_id', $this->item->getKey())
            ->sole();

        $this->assertEquals(2.0, (float) $productMovement->quantity);
        $this->assertSame($batch->getKey(), $productMovement->production_batch_id);
        $this->assertSame('BATCH-BC-01 / WB005', $productMovement->reference);

        $secondMovement = InventoryMovement::query()
            ->where('item_id', $secondItem->getKey())
            ->sole();

        $this->assertEquals(1.0, (float) $secondMovement->quantity);
        $this->assertSame(3, ExternalSyncedBarang::query()->count());
        $this->assertDatabaseMissing('external_synced_barangs', [
            'kode_barang' => 'BC002-WB005-241126-0504',
        ]);
        $this->assertEquals(2.0, (float) InventoryBalance::query()
            ->where('item_id', $this->item->getKey())
            ->where('warehouse_id', $this->warehouse->getKey())
            ->sole()
            ->quantity_on_hand);
    }

    public function test_sync_batch_output_matches_batch_id_and_reports_unmapped_variants(): void
    {
        $batch = ProductionBatch::factory()->create();

        Http::fake([
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 511,
                        'kodeBarang' => 'WB005-241126-0511',
                        'variantId' => 5,
                        'batchId' => 7,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-22T10:00:00.000Z',
                    ],
                    [
                        'id' => 512,
                        'kodeBarang' => 'WB999-241126-0512',
                        'variantId' => 999,
                        'batchId' => 7,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-22T11:00:00.000Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        $apiService = new ExternalBarangApiService('http://localhost:8000');
        $syncService = new ExternalBarangSyncService($apiService, app(StockMovementService::class));

        $result = $syncService->syncBatchOutput($this->warehouse, '7', [
            'production_batch_id' => $batch->getKey(),
        ]);

        $this->assertSame(1, $result['synced_count']);
        $this->assertCount(1, $result['movements']);
        $this->assertCount(1, $result['unmapped']);
        $this->assertSame(999, $result['unmapped'][0]['external_variant_id']);
        $this->assertSame(1, $result['unmapped'][0]['unit_count']);
        $this->assertDatabaseMissing('external_synced_barangs', [
            'kode_barang' => 'WB999-241126-0512',
        ]);
    }

    public function test_sync_batch_output_fails_when_no_units_match(): void
    {
        $batch = ProductionBatch::factory()->create();

        Http::fake([
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 521,
                        'kodeBarang' => 'BC009-WB005-241126-0521',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-23T10:00:00.000Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        $apiService = new ExternalBarangApiService('http://localhost:8000');
        $syncService = new ExternalBarangSyncService($apiService, app(StockMovementService::class));

        $this->expectException(ValidationException::class);

        $syncService->syncBatchOutput($this->warehouse, 'BC001', [
            'production_batch_id' => $batch->getKey(),
        ]);
    }

    public function test_sync_respects_external_batch_code_filter(): void
    {
        Http::fake([
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 531,
                        'kodeBarang' => 'BC001-WB005-241126-0531',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-23T10:00:00.000Z',
                    ],
                    [
                        'id' => 532,
                        'kodeBarang' => 'BC002-WB005-241126-0532',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-23T11:00:00.000Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        $apiService = new ExternalBarangApiService('http://localhost:8000');
        $syncService = new ExternalBarangSyncService($apiService, app(StockMovementService::class));

        $result = $syncService->sync($this->warehouse, ['batch_code' => 'BC001']);

        $this->assertSame(1, $result['synced_count']);
        $this->assertDatabaseHas('external_synced_barangs', [
            'kode_barang' => 'BC001-WB005-241126-0531',
        ]);
        $this->assertDatabaseMissing('external_synced_barangs', [
            'kode_barang' => 'BC002-WB005-241126-0532',
        ]);
    }

    public function test_sync_batch_output_respects_requested_quantity_per_variant(): void
    {
        $batch = ProductionBatch::factory()->create(['batch_code' => 'BATCH-QTY-01']);

        $secondItem = Item::factory()->create([
            'item_code' => 'G00003',
            'item_name' => 'Windbreaker Motif Navy MD',
        ]);

        ExternalItemMapping::create([
            'item_id' => $secondItem->getKey(),
            'external_variant_id' => 6,
            'external_variant_code' => 'WB006',
            'is_active' => true,
        ]);

        Http::fake([
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 601,
                        'kodeBarang' => 'BC001-WB005-241126-0601',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-20T10:00:00.000Z',
                    ],
                    [
                        'id' => 602,
                        'kodeBarang' => 'BC001-WB005-241126-0602',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-20T11:00:00.000Z',
                    ],
                    [
                        'id' => 603,
                        'kodeBarang' => 'BC001-WB005-241126-0603',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-20T12:00:00.000Z',
                    ],
                    [
                        'id' => 604,
                        'kodeBarang' => 'BC001-WB006-241126-0604',
                        'variantId' => 6,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-21T10:00:00.000Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        $apiService = new ExternalBarangApiService('http://localhost:8000');
        $syncService = new ExternalBarangSyncService($apiService, app(StockMovementService::class));

        $result = $syncService->syncBatchOutput($this->warehouse, 'BC001', [
            'production_batch_id' => $batch->getKey(),
            'quantities' => [5 => 2, 6 => 0],
        ]);

        $this->assertSame(2, $result['synced_count']);
        $this->assertCount(1, $result['movements']);
        $this->assertSame(5, $result['groups'][0]['external_variant_id']);
        $this->assertSame(2, $result['groups'][0]['quantity']);

        $movement = InventoryMovement::query()->sole();
        $this->assertSame($this->item->getKey(), $movement->item_id);
        $this->assertEquals(2.0, (float) $movement->quantity);
        $this->assertSame('BATCH-QTY-01 / WB005', $movement->reference);

        $this->assertSame(2, ExternalSyncedBarang::query()->count());
        $this->assertDatabaseHas('external_synced_barangs', ['kode_barang' => 'BC001-WB005-241126-0601']);
        $this->assertDatabaseHas('external_synced_barangs', ['kode_barang' => 'BC001-WB005-241126-0602']);
        $this->assertDatabaseMissing('external_synced_barangs', ['kode_barang' => 'BC001-WB005-241126-0603']);
        $this->assertDatabaseMissing('external_synced_barangs', ['kode_barang' => 'BC001-WB006-241126-0604']);
    }

    public function test_sync_variant_output_limits_units_to_requested_quantity(): void
    {
        $batch = ProductionBatch::factory()->create(['batch_code' => 'BATCH-QTY-02']);

        Http::fake([
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 611,
                        'kodeBarang' => 'BC001-WB005-241126-0611',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-20T10:00:00.000Z',
                    ],
                    [
                        'id' => 612,
                        'kodeBarang' => 'BC001-WB005-241126-0612',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-20T11:00:00.000Z',
                    ],
                    [
                        'id' => 613,
                        'kodeBarang' => 'BC001-WB005-241126-0613',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-20T12:00:00.000Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        $apiService = new ExternalBarangApiService('http://localhost:8000');
        $syncService = new ExternalBarangSyncService($apiService, app(StockMovementService::class));

        $result = $syncService->syncVariantOutput($this->warehouse, 5, [
            'production_batch_id' => $batch->getKey(),
            'quantity' => 2,
        ]);

        $this->assertSame(2, $result['synced_count']);
        $this->assertEquals(2.0, (float) $result['movement']->quantity);
        $this->assertSame(2, ExternalSyncedBarang::query()->count());
        $this->assertDatabaseMissing('external_synced_barangs', ['kode_barang' => 'BC001-WB005-241126-0613']);
    }

    public function test_artisan_command_executes_sync_successfully(): void
    {
        Http::fake([
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 106,
                        'kodeBarang' => 'BC001-WB005-181126-0006',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'tanggal' => '2026-11-17T17:19:42.416Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        $this->artisan('sync:external-barang', [
            '--warehouse' => $this->warehouse->getKey(),
        ])
            ->assertSuccessful();

        $this->assertDatabaseHas('external_synced_barangs', [
            'kode_barang' => 'BC001-WB005-181126-0006',
        ]);
    }
}
