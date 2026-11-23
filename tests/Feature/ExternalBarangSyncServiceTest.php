<?php

namespace Tests\Feature;

use App\Models\ExternalItemMapping;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ExternalBarangApiService;
use App\Services\ExternalBarangSyncService;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
