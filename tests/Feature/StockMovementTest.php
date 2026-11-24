<?php

namespace Tests\Feature;

use App\Filament\Pages\StockCard;
use App\Filament\Resources\InventoryMovements\Pages\CreateInventoryMovement;
use App\Filament\Resources\InventoryMovements\Pages\ListInventoryMovements;
use App\Models\ExternalItemMapping;
use App\Models\ExternalSyncedBarang;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\ItemComponent;
use App\Models\ProductionBatch;
use App\Models\ProductionBatchItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\BatchRequirementService;
use App\Services\ExternalBarangSyncService;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_incoming_movement_increases_physical_stock(): void
    {
        $balance = InventoryBalance::factory()->create([
            'quantity_on_hand' => 100,
        ]);

        app(StockMovementService::class)->record([
            'type' => InventoryMovement::TYPE_IN_PURCHASE,
            'item_id' => $balance->item_id,
            'warehouse_id' => $balance->warehouse_id,
            'quantity' => 25,
        ]);

        $this->assertSame(125.0, (float) $balance->fresh()->quantity_on_hand);
    }

    public function test_outgoing_movement_decreases_physical_stock(): void
    {
        $balance = InventoryBalance::factory()->create([
            'quantity_on_hand' => 100,
        ]);

        app(StockMovementService::class)->record([
            'type' => InventoryMovement::TYPE_OUT_PRODUCTION,
            'item_id' => $balance->item_id,
            'warehouse_id' => $balance->warehouse_id,
            'quantity' => 40,
        ]);

        $this->assertSame(60.0, (float) $balance->fresh()->quantity_on_hand);
    }

    public function test_outgoing_movement_is_rejected_when_stock_is_insufficient(): void
    {
        $balance = InventoryBalance::factory()->create([
            'quantity_on_hand' => 5,
        ]);

        $this->expectException(ValidationException::class);

        app(StockMovementService::class)->record([
            'type' => InventoryMovement::TYPE_OUT_PRODUCTION,
            'item_id' => $balance->item_id,
            'warehouse_id' => $balance->warehouse_id,
            'quantity' => 10,
        ]);
    }

    public function test_stock_transfer_moves_stock_between_warehouses(): void
    {
        $item = Item::factory()->create();
        $origin = Warehouse::factory()->create();
        $destination = Warehouse::factory()->create();

        InventoryBalance::factory()->create([
            'warehouse_id' => $origin->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 50,
        ]);

        [$out, $in] = app(StockMovementService::class)->transfer([
            'item_id' => $item->getKey(),
            'warehouse_id' => $origin->getKey(),
            'destination_warehouse_id' => $destination->getKey(),
            'quantity' => 20,
        ]);

        $this->assertSame(InventoryMovement::TYPE_TRANSFER_OUT, $out->type);
        $this->assertSame(InventoryMovement::TYPE_TRANSFER_IN, $in->type);
        $this->assertSame($out->transfer_group_id, $in->transfer_group_id);

        $this->assertSame(30.0, (float) $this->balanceOf($item, $origin)->quantity_on_hand);
        $this->assertSame(20.0, (float) $this->balanceOf($item, $destination)->quantity_on_hand);
    }

    public function test_deleting_a_transfer_restores_both_warehouse_balances(): void
    {
        $item = Item::factory()->create();
        $origin = Warehouse::factory()->create();
        $destination = Warehouse::factory()->create();

        InventoryBalance::factory()->create([
            'warehouse_id' => $origin->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 15,
        ]);

        [$out] = app(StockMovementService::class)->transfer([
            'item_id' => $item->getKey(),
            'warehouse_id' => $origin->getKey(),
            'destination_warehouse_id' => $destination->getKey(),
            'quantity' => 15,
        ]);

        app(StockMovementService::class)->delete($out);

        $this->assertSame(0, InventoryMovement::query()->count());
        $this->assertSame(15.0, (float) $this->balanceOf($item, $origin)->quantity_on_hand);
        $this->assertSame(0.0, (float) $this->balanceOf($item, $destination)->quantity_on_hand);
    }

    public function test_deleting_a_movement_reverses_its_effect(): void
    {
        $balance = InventoryBalance::factory()->create([
            'quantity_on_hand' => 10,
        ]);

        $movement = app(StockMovementService::class)->record([
            'type' => InventoryMovement::TYPE_IN_PURCHASE,
            'item_id' => $balance->item_id,
            'warehouse_id' => $balance->warehouse_id,
            'quantity' => 5,
        ]);

        app(StockMovementService::class)->delete($movement);

        $this->assertSame(10.0, (float) $balance->fresh()->quantity_on_hand);
    }

    public function test_stock_transaction_can_be_created_through_filament(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create();

        Livewire::test(CreateInventoryMovement::class)
            ->fillForm([
                'type' => InventoryMovement::TYPE_IN_PURCHASE,
                'warehouse_id' => $warehouse->getKey(),
                'item_id' => $item->getKey(),
                'quantity' => 75,
                'occurred_at' => now(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('inventory_movements', [
            'type' => InventoryMovement::TYPE_IN_PURCHASE,
            'item_id' => $item->getKey(),
            'warehouse_id' => $warehouse->getKey(),
        ]);

        $this->assertSame(75.0, (float) $this->balanceOf($item, $warehouse)->quantity_on_hand);
    }

    public function test_stock_transfer_can_be_created_through_filament(): void
    {
        $origin = Warehouse::factory()->create();
        $destination = Warehouse::factory()->create();
        $balance = InventoryBalance::factory()->create([
            'warehouse_id' => $origin->getKey(),
            'quantity_on_hand' => 40,
        ]);

        Livewire::test(CreateInventoryMovement::class)
            ->fillForm([
                'type' => InventoryMovement::TYPE_TRANSFER,
                'warehouse_id' => $origin->getKey(),
                'destination_warehouse_id' => $destination->getKey(),
                'item_id' => $balance->item_id,
                'quantity' => 15,
                'occurred_at' => now(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(25.0, (float) $balance->fresh()->quantity_on_hand);
        $this->assertSame(2, InventoryMovement::query()->count());
    }

    public function test_production_output_consumes_materials_automatically(): void
    {
        [$product, $first, $second, $batch, $materialWarehouse, $outputWarehouse] = $this->productionFixture();

        app(BatchRequirementService::class)->recalculate($batch);

        Livewire::test(CreateInventoryMovement::class)
            ->fillForm([
                'type' => InventoryMovement::TYPE_IN_PRODUCTION,
                'source_external' => false,
                'warehouse_id' => $outputWarehouse->getKey(),
                'item_id' => $product->getKey(),
                'production_batch_id' => $batch->getKey(),
                'quantity' => 10,
                'occurred_at' => now(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(3, InventoryMovement::query()->count());

        $output = InventoryMovement::query()
            ->where('type', InventoryMovement::TYPE_IN_PRODUCTION)
            ->sole();

        $consumptions = InventoryMovement::query()
            ->where('type', InventoryMovement::TYPE_OUT_PRODUCTION)
            ->where('parent_movement_id', $output->getKey())
            ->get()
            ->keyBy('item_id');

        $this->assertCount(2, $consumptions);
        $this->assertSame(10.0, (float) $consumptions[$first->getKey()]->quantity);
        $this->assertSame(20.0, (float) $consumptions[$second->getKey()]->quantity);
        $this->assertSame($batch->getKey(), $consumptions[$first->getKey()]->production_batch_id);

        $this->assertSame(10.0, (float) $this->balanceOf($product, $outputWarehouse)->quantity_on_hand);
        $this->assertSame(40.0, (float) $this->balanceOf($first, $materialWarehouse)->quantity_on_hand);
        $this->assertSame(80.0, (float) $this->balanceOf($second, $materialWarehouse)->quantity_on_hand);

        $materials = $batch->materials()->get()->keyBy('item_id');
        $this->assertSame(10.0, $materials[$first->getKey()]->actual_quantity);
        $this->assertSame(20.0, $materials[$second->getKey()]->actual_quantity);
    }

    public function test_material_consumption_can_be_disabled_for_production_output(): void
    {
        [$product, $first, $second, $batch, $materialWarehouse, $outputWarehouse] = $this->productionFixture();

        Livewire::test(CreateInventoryMovement::class)
            ->fillForm([
                'type' => InventoryMovement::TYPE_IN_PRODUCTION,
                'source_external' => false,
                'warehouse_id' => $outputWarehouse->getKey(),
                'item_id' => $product->getKey(),
                'production_batch_id' => $batch->getKey(),
                'quantity' => 10,
                'consume_components' => false,
                'occurred_at' => now(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, InventoryMovement::query()->count());
        $this->assertSame(50.0, (float) $this->balanceOf($first, $materialWarehouse)->quantity_on_hand);
        $this->assertSame(100.0, (float) $this->balanceOf($second, $materialWarehouse)->quantity_on_hand);
    }

    public function test_production_output_is_rejected_when_material_stock_is_insufficient(): void
    {
        [$product, $first, $second, $batch, $materialWarehouse, $outputWarehouse] = $this->productionFixture();

        $this->balanceOf($first, $materialWarehouse)->update(['quantity_on_hand' => 5]);

        Livewire::test(CreateInventoryMovement::class)
            ->fillForm([
                'type' => InventoryMovement::TYPE_IN_PRODUCTION,
                'source_external' => false,
                'warehouse_id' => $outputWarehouse->getKey(),
                'item_id' => $product->getKey(),
                'production_batch_id' => $batch->getKey(),
                'quantity' => 10,
                'occurred_at' => now(),
            ])
            ->call('create')
            ->assertHasFormErrors(['quantity']);

        $this->assertSame(0, InventoryMovement::query()->count());
        $this->assertSame(5.0, (float) $this->balanceOf($first, $materialWarehouse)->quantity_on_hand);
        $this->assertSame(100.0, (float) $this->balanceOf($second, $materialWarehouse)->quantity_on_hand);
    }

    public function test_production_output_can_be_recorded_from_external_api(): void
    {
        [$product, $first, $second, $batch, $materialWarehouse, $outputWarehouse] = $this->productionFixture();

        ExternalItemMapping::create([
            'item_id' => $product->getKey(),
            'external_variant_id' => 5,
            'external_variant_code' => 'WB005',
            'is_active' => true,
        ]);

        Http::fake([
            '*api/public/barang/rekap-variant*' => Http::response([
                'data' => [],
                'statuses' => ['REGISTER', 'FINISHGOOD'],
                'total' => 0,
            ], 200),
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 401,
                        'kodeBarang' => 'BC001-WB005-241126-0401',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-22T10:00:00.000Z',
                    ],
                    [
                        'id' => 402,
                        'kodeBarang' => 'BC001-WB005-241126-0402',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-23T10:00:00.000Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        Livewire::test(CreateInventoryMovement::class)
            ->fillForm([
                'type' => InventoryMovement::TYPE_IN_PRODUCTION,
                'source_external' => true,
                'warehouse_id' => $outputWarehouse->getKey(),
                'production_batch_id' => $batch->getKey(),
                'external_variant_id' => 5,
                'external_status' => 'FINISHGOOD',
                'tanggal_awal' => '2026-11-01',
                'tanggal_akhir' => '2026-11-30',
                'occurred_at' => now(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $output = InventoryMovement::query()
            ->where('type', InventoryMovement::TYPE_IN_PRODUCTION)
            ->sole();

        $this->assertEquals(2.0, (float) $output->quantity);
        $this->assertSame($batch->getKey(), $output->production_batch_id);
        $this->assertSame(0, InventoryMovement::query()
            ->where('type', InventoryMovement::TYPE_OUT_PRODUCTION)
            ->count());
        $this->assertSame(2.0, (float) $this->balanceOf($product, $outputWarehouse)->quantity_on_hand);
        $this->assertSame(2, ExternalSyncedBarang::query()->count());
    }

    public function test_production_output_can_be_pulled_by_external_batch_code(): void
    {
        [$product, $first, $second, $batch, $materialWarehouse, $outputWarehouse] = $this->productionFixture();

        $secondProduct = Item::factory()->create([
            'item_code' => 'G00009',
            'item_name' => 'Windbreaker Motif Navy MD',
        ]);

        ExternalItemMapping::create([
            'item_id' => $product->getKey(),
            'external_variant_id' => 5,
            'external_variant_code' => 'WB005',
            'is_active' => true,
        ]);

        ExternalItemMapping::create([
            'item_id' => $secondProduct->getKey(),
            'external_variant_id' => 6,
            'external_variant_code' => 'WB006',
            'is_active' => true,
        ]);

        Http::fake([
            '*api/public/barang/rekap-variant*' => Http::response([
                'data' => [],
                'statuses' => ['REGISTER', 'FINISHGOOD'],
                'total' => 0,
            ], 200),
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 411,
                        'kodeBarang' => 'BC001-WB005-241126-0411',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-22T10:00:00.000Z',
                    ],
                    [
                        'id' => 412,
                        'kodeBarang' => 'BC001-WB005-241126-0412',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-22T11:00:00.000Z',
                    ],
                    [
                        'id' => 413,
                        'kodeBarang' => 'BC001-WB006-241126-0413',
                        'variantId' => 6,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-23T10:00:00.000Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        Livewire::test(CreateInventoryMovement::class)
            ->fillForm([
                'type' => InventoryMovement::TYPE_IN_PRODUCTION,
                'source_external' => true,
                'warehouse_id' => $outputWarehouse->getKey(),
                'production_batch_id' => $batch->getKey(),
                'external_batch_code' => 'BC001',
                'external_status' => 'FINISHGOOD',
                'tanggal_awal' => '2026-11-01',
                'tanggal_akhir' => '2026-11-30',
                'occurred_at' => now(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $movements = InventoryMovement::query()
            ->where('type', InventoryMovement::TYPE_IN_PRODUCTION)
            ->get();

        $this->assertCount(2, $movements);

        $productMovement = $movements->firstWhere('item_id', $product->getKey());
        $this->assertEquals(2.0, (float) $productMovement->quantity);
        $this->assertSame($batch->getKey(), $productMovement->production_batch_id);

        $secondMovement = $movements->firstWhere('item_id', $secondProduct->getKey());
        $this->assertEquals(1.0, (float) $secondMovement->quantity);

        $this->assertSame(0, InventoryMovement::query()
            ->where('type', InventoryMovement::TYPE_OUT_PRODUCTION)
            ->count());
        $this->assertSame(3, ExternalSyncedBarang::query()->count());
        $this->assertSame(2.0, (float) $this->balanceOf($product, $outputWarehouse)->quantity_on_hand);
        $this->assertSame(1.0, (float) $this->balanceOf($secondProduct, $outputWarehouse)->quantity_on_hand);
    }

    public function test_production_output_variant_pull_respects_entered_quantity(): void
    {
        [$product, $first, $second, $batch, $materialWarehouse, $outputWarehouse] = $this->productionFixture();

        ExternalItemMapping::create([
            'item_id' => $product->getKey(),
            'external_variant_id' => 5,
            'external_variant_code' => 'WB005',
            'is_active' => true,
        ]);

        Http::fake([
            '*api/public/barang/rekap-variant*' => Http::response([
                'data' => [],
                'statuses' => ['REGISTER', 'FINISHGOOD'],
                'total' => 0,
            ], 200),
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 421,
                        'kodeBarang' => 'BC001-WB005-241126-0421',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-22T10:00:00.000Z',
                    ],
                    [
                        'id' => 422,
                        'kodeBarang' => 'BC001-WB005-241126-0422',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-22T11:00:00.000Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        Livewire::test(CreateInventoryMovement::class)
            ->fillForm([
                'type' => InventoryMovement::TYPE_IN_PRODUCTION,
                'source_external' => true,
                'warehouse_id' => $outputWarehouse->getKey(),
                'production_batch_id' => $batch->getKey(),
                'external_variant_id' => 5,
                'external_quantity' => 1,
                'external_status' => 'FINISHGOOD',
                'occurred_at' => now(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $output = InventoryMovement::query()
            ->where('type', InventoryMovement::TYPE_IN_PRODUCTION)
            ->sole();

        $this->assertEquals(1.0, (float) $output->quantity);
        $this->assertSame(1, ExternalSyncedBarang::query()->count());
        $this->assertDatabaseHas('external_synced_barangs', ['kode_barang' => 'BC001-WB005-241126-0421']);
        $this->assertDatabaseMissing('external_synced_barangs', ['kode_barang' => 'BC001-WB005-241126-0422']);
        $this->assertSame(1.0, (float) $this->balanceOf($product, $outputWarehouse)->quantity_on_hand);
    }

    public function test_production_output_batch_pull_respects_entered_quantity_per_variant(): void
    {
        [$product, $first, $second, $batch, $materialWarehouse, $outputWarehouse] = $this->productionFixture();

        $secondProduct = Item::factory()->create([
            'item_code' => 'G00009',
            'item_name' => 'Windbreaker Motif Navy MD',
        ]);

        ExternalItemMapping::create([
            'item_id' => $product->getKey(),
            'external_variant_id' => 5,
            'external_variant_code' => 'WB005',
            'is_active' => true,
        ]);

        ExternalItemMapping::create([
            'item_id' => $secondProduct->getKey(),
            'external_variant_id' => 6,
            'external_variant_code' => 'WB006',
            'is_active' => true,
        ]);

        Http::fake([
            '*api/public/barang/rekap-variant*' => Http::response([
                'data' => [],
                'statuses' => ['REGISTER', 'FINISHGOOD'],
                'total' => 0,
            ], 200),
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 431,
                        'kodeBarang' => 'BC001-WB005-241126-0431',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-22T10:00:00.000Z',
                    ],
                    [
                        'id' => 432,
                        'kodeBarang' => 'BC001-WB005-241126-0432',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-22T11:00:00.000Z',
                    ],
                    [
                        'id' => 433,
                        'kodeBarang' => 'BC001-WB005-241126-0433',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-22T12:00:00.000Z',
                    ],
                    [
                        'id' => 434,
                        'kodeBarang' => 'BC001-WB006-241126-0434',
                        'variantId' => 6,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-23T10:00:00.000Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        Livewire::test(CreateInventoryMovement::class)
            ->fillForm([
                'type' => InventoryMovement::TYPE_IN_PRODUCTION,
                'source_external' => true,
                'warehouse_id' => $outputWarehouse->getKey(),
                'production_batch_id' => $batch->getKey(),
                'external_batch_code' => 'BC001',
                'tanggal_awal' => '2026-11-01',
                'tanggal_akhir' => '2026-11-30',
                'external_status' => 'FINISHGOOD',
                'external_quantities' => [
                    ['external_variant_id' => 5, 'max_quantity' => 3, 'quantity' => 1],
                    ['external_variant_id' => 6, 'max_quantity' => 1, 'quantity' => 0],
                ],
                'occurred_at' => now(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $movement = InventoryMovement::query()
            ->where('type', InventoryMovement::TYPE_IN_PRODUCTION)
            ->sole();

        $this->assertSame($product->getKey(), $movement->item_id);
        $this->assertEquals(1.0, (float) $movement->quantity);
        $this->assertSame(1, ExternalSyncedBarang::query()->count());
        $this->assertDatabaseHas('external_synced_barangs', ['kode_barang' => 'BC001-WB005-241126-0431']);
        $this->assertDatabaseMissing('external_synced_barangs', ['kode_barang' => 'BC001-WB006-241126-0434']);
        $this->assertSame(0, InventoryMovement::query()
            ->where('item_id', $secondProduct->getKey())
            ->count());
    }

    public function test_deleting_external_output_releases_units_for_the_next_pull(): void
    {
        [$product, $first, $second, $batch, $materialWarehouse, $outputWarehouse] = $this->productionFixture();

        ExternalItemMapping::create([
            'item_id' => $product->getKey(),
            'external_variant_id' => 5,
            'external_variant_code' => 'WB005',
            'is_active' => true,
        ]);

        Http::fake([
            '*api/public/barang*' => Http::response([
                'data' => [
                    [
                        'id' => 441,
                        'kodeBarang' => 'BC001-WB005-241126-0441',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-22T10:00:00.000Z',
                    ],
                    [
                        'id' => 442,
                        'kodeBarang' => 'BC001-WB005-241126-0442',
                        'variantId' => 5,
                        'status' => 'FINISHGOOD',
                        'finishGoodAt' => '2026-11-22T11:00:00.000Z',
                    ],
                ],
                'meta' => ['totalPages' => 1],
            ], 200),
        ]);

        $syncService = app(ExternalBarangSyncService::class);
        $movementService = app(StockMovementService::class);

        $first = $syncService->syncVariantOutput($outputWarehouse, 5, [
            'production_batch_id' => $batch->getKey(),
        ]);

        $this->assertSame(2, $first['synced_count']);
        $this->assertSame(2, ExternalSyncedBarang::query()->count());

        $movementService->delete($first['movement']);

        $this->assertSame(0, InventoryMovement::query()->count());
        $this->assertSame(0, ExternalSyncedBarang::query()->count());
        $this->assertSame(0.0, (float) $this->balanceOf($product, $outputWarehouse)->quantity_on_hand);

        $second = $syncService->syncVariantOutput($outputWarehouse, 5, [
            'production_batch_id' => $batch->getKey(),
        ]);

        $this->assertSame(2, $second['synced_count']);
        $this->assertEquals(2.0, (float) $second['movement']->quantity);
        $this->assertSame(2, ExternalSyncedBarang::query()->count());
    }

    public function test_deleting_production_output_restores_output_and_material_stock(): void
    {
        [$product, $first, $second, $batch, $materialWarehouse, $outputWarehouse] = $this->productionFixture();

        $service = app(StockMovementService::class);

        $output = $service->recordProductionOutput([
            'type' => InventoryMovement::TYPE_IN_PRODUCTION,
            'warehouse_id' => $outputWarehouse->getKey(),
            'item_id' => $product->getKey(),
            'production_batch_id' => $batch->getKey(),
            'quantity' => 10,
        ]);

        $service->delete($output);

        $this->assertSame(0, InventoryMovement::query()->count());
        $this->assertSame(0.0, (float) $this->balanceOf($product, $outputWarehouse)->quantity_on_hand);
        $this->assertSame(50.0, (float) $this->balanceOf($first, $materialWarehouse)->quantity_on_hand);
        $this->assertSame(100.0, (float) $this->balanceOf($second, $materialWarehouse)->quantity_on_hand);
    }

    public function test_stock_card_page_renders(): void
    {
        InventoryMovement::factory()->create();

        $this->get('/admin/stock-card')->assertOk();
    }

    public function test_stock_card_can_be_filtered_by_production_batch(): void
    {
        $batch = ProductionBatch::factory()->create();
        $item = Item::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $withBatch = InventoryMovement::factory()->create([
            'item_id' => $item->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'production_batch_id' => $batch->getKey(),
        ]);
        $withoutBatch = InventoryMovement::factory()->create([
            'item_id' => $item->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'production_batch_id' => null,
        ]);

        Livewire::test(StockCard::class)
            ->assertTableFilterExists('production_batch_id')
            ->filterTable('production_batch_id', $batch->getKey())
            ->assertCanSeeTableRecords([$withBatch])
            ->assertCanNotSeeTableRecords([$withoutBatch]);
    }

    public function test_stock_transaction_can_be_deleted_from_the_table(): void
    {
        $balance = InventoryBalance::factory()->create([
            'quantity_on_hand' => 10,
        ]);

        $movement = InventoryMovement::factory()->create([
            'type' => InventoryMovement::TYPE_IN_PURCHASE,
            'item_id' => $balance->item_id,
            'warehouse_id' => $balance->warehouse_id,
            'quantity' => 4,
        ]);

        Livewire::test(ListInventoryMovements::class)
            ->callTableAction('delete', $movement)
            ->assertNotified('Transaksi dihapus');

        $this->assertDatabaseMissing('inventory_movements', ['id' => $movement->getKey()]);
        $this->assertSame(6.0, (float) $balance->fresh()->quantity_on_hand);
    }

    public function test_deleting_an_already_consumed_incoming_movement_is_rejected_with_a_clear_message(): void
    {
        $balance = InventoryBalance::factory()->create([
            'quantity_on_hand' => 100,
        ]);

        $service = app(StockMovementService::class);

        $incoming = $service->record([
            'type' => InventoryMovement::TYPE_IN_PURCHASE,
            'item_id' => $balance->item_id,
            'warehouse_id' => $balance->warehouse_id,
            'quantity' => 10,
        ]);

        $service->record([
            'type' => InventoryMovement::TYPE_OUT_OTHER,
            'item_id' => $balance->item_id,
            'warehouse_id' => $balance->warehouse_id,
            'quantity' => 105,
        ]);

        Livewire::test(ListInventoryMovements::class)
            ->callTableAction('delete', $incoming)
            ->assertNotified('Transaksi tidak bisa dihapus');

        $this->assertDatabaseHas('inventory_movements', ['id' => $incoming->getKey()]);
        $this->assertSame(5.0, (float) $balance->fresh()->quantity_on_hand);
    }

    private function balanceOf(Item $item, Warehouse $warehouse): InventoryBalance
    {
        return InventoryBalance::query()
            ->where('item_id', $item->getKey())
            ->where('warehouse_id', $warehouse->getKey())
            ->firstOrFail();
    }

    /**
     * Produk dengan 2 komponen: komponen pertama 1 per unit, kedua 2 per unit.
     *
     * @return array{0: Item, 1: Item, 2: Item, 3: ProductionBatch, 4: Warehouse, 5: Warehouse}
     */
    private function productionFixture(): array
    {
        $product = Item::factory()->create();
        $first = Item::factory()->create();
        $second = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $product->getKey(),
            'component_item_id' => $first->getKey(),
            'quantity' => 1,
        ]);
        ItemComponent::factory()->create([
            'parent_item_id' => $product->getKey(),
            'component_item_id' => $second->getKey(),
            'quantity' => 2,
        ]);

        $materialWarehouse = Warehouse::factory()->create();
        $outputWarehouse = Warehouse::factory()->create();

        InventoryBalance::factory()->create([
            'warehouse_id' => $materialWarehouse->getKey(),
            'item_id' => $first->getKey(),
            'quantity_on_hand' => 50,
        ]);
        InventoryBalance::factory()->create([
            'warehouse_id' => $materialWarehouse->getKey(),
            'item_id' => $second->getKey(),
            'quantity_on_hand' => 100,
        ]);

        $batch = ProductionBatch::factory()->create([
            'material_warehouse_id' => $materialWarehouse->getKey(),
            'output_warehouse_id' => $outputWarehouse->getKey(),
        ]);

        ProductionBatchItem::factory()->create([
            'production_batch_id' => $batch->getKey(),
            'item_id' => $product->getKey(),
            'quantity_planned' => 100,
        ]);

        return [$product, $first, $second, $batch, $materialWarehouse, $outputWarehouse];
    }
}
