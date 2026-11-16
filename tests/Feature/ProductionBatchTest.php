<?php

namespace Tests\Feature;

use App\Filament\Pages\BatchRealization;
use App\Filament\Pages\BatchRealizationDetail;
use App\Filament\Resources\Items\Pages\EditItem;
use App\Filament\Resources\Items\RelationManagers\ItemComponentsRelationManager;
use App\Filament\Resources\ProductionBatches\Pages\CreateProductionBatch;
use App\Filament\Resources\ProductionBatches\Pages\ViewProductionBatch;
use App\Filament\Resources\ProductionBatches\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\ProductionBatches\RelationManagers\MaterialsRelationManager;
use App\Filament\Resources\ProductionBatches\RelationManagers\RawMaterialsRelationManager;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\ItemComponent;
use App\Models\ProductionBatch;
use App\Models\ProductionBatchItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\BatchRequirementService;
use App\Services\BomExplosionService;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductionBatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_material_requirements_are_aggregated_from_item_components(): void
    {
        $product = Item::factory()->create();
        $visor = Item::factory()->create();
        $strap = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $product->getKey(),
            'component_item_id' => $visor->getKey(),
            'quantity' => 1,
        ]);
        ItemComponent::factory()->create([
            'parent_item_id' => $product->getKey(),
            'component_item_id' => $strap->getKey(),
            'quantity' => 2,
        ]);

        $batch = ProductionBatch::factory()->create();
        ProductionBatchItem::factory()->create([
            'production_batch_id' => $batch->getKey(),
            'item_id' => $product->getKey(),
            'quantity_planned' => 5000,
        ]);

        app(BatchRequirementService::class)->recalculate($batch);

        $materials = $batch->materials()->get()->keyBy('item_id');

        $this->assertCount(2, $materials);
        $this->assertSame(5000.0, (float) $materials[$visor->getKey()]->quantity_required);
        $this->assertSame(10000.0, (float) $materials[$strap->getKey()]->quantity_required);
    }

    public function test_recalculate_updates_and_removes_obsolete_materials(): void
    {
        $product = Item::factory()->create();
        $visor = Item::factory()->create();
        $strap = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $product->getKey(),
            'component_item_id' => $visor->getKey(),
            'quantity' => 1,
        ]);
        $strapComponent = ItemComponent::factory()->create([
            'parent_item_id' => $product->getKey(),
            'component_item_id' => $strap->getKey(),
            'quantity' => 2,
        ]);

        $batch = ProductionBatch::factory()->create();
        ProductionBatchItem::factory()->create([
            'production_batch_id' => $batch->getKey(),
            'item_id' => $product->getKey(),
            'quantity_planned' => 100,
        ]);

        $service = app(BatchRequirementService::class);
        $service->recalculate($batch);

        $strapComponent->delete();
        $service->recalculate($batch);

        $materials = $batch->materials()->get()->keyBy('item_id');

        $this->assertCount(1, $materials);
        $this->assertArrayNotHasKey($strap->getKey(), $materials->all());
    }

    public function test_material_shortage_is_calculated_from_stock_on_hand(): void
    {
        $materialWarehouse = Warehouse::factory()->create();
        $product = Item::factory()->create();
        $component = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $product->getKey(),
            'component_item_id' => $component->getKey(),
            'quantity' => 1,
        ]);

        InventoryBalance::factory()->create([
            'warehouse_id' => $materialWarehouse->getKey(),
            'item_id' => $component->getKey(),
            'quantity_on_hand' => 50,
        ]);

        $batch = ProductionBatch::factory()->create([
            'material_warehouse_id' => $materialWarehouse->getKey(),
        ]);
        ProductionBatchItem::factory()->create([
            'production_batch_id' => $batch->getKey(),
            'item_id' => $product->getKey(),
            'quantity_planned' => 100,
        ]);

        app(BatchRequirementService::class)->recalculate($batch);

        $material = $batch->materials()->firstOrFail();

        $this->assertSame(50.0, $material->stock_on_hand);
        $this->assertSame(50.0, $material->shortage_quantity);
    }

    public function test_composition_cycle_is_prevented(): void
    {
        $first = Item::factory()->create();
        $second = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $first->getKey(),
            'component_item_id' => $second->getKey(),
        ]);

        $this->assertTrue(ItemComponent::wouldCreateCycle($second->getKey(), $first->getKey()));
        $this->assertTrue(ItemComponent::wouldCreateCycle($first->getKey(), $first->getKey()));
        $this->assertFalse(ItemComponent::wouldCreateCycle($first->getKey(), $second->getKey()));
    }

    public function test_production_batch_pages_render(): void
    {
        $batch = ProductionBatch::factory()->create();

        $urls = [
            '/admin/production-batches',
            '/admin/production-batches/create',
            "/admin/production-batches/{$batch->getKey()}",
            "/admin/production-batches/{$batch->getKey()}/edit",
            '/admin/batch-realization',
            "/admin/batch-realization/{$batch->getKey()}",
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }

        $this->get("/admin/production-batches/{$batch->getKey()}")
            ->assertOk()
            ->assertSee('Rincian bahan baku');
    }

    public function test_batch_realization_report_links_to_the_detail_page(): void
    {
        $batch = ProductionBatch::factory()->create();

        Livewire::test(BatchRealization::class)
            ->assertTableActionHasUrl(
                'detail',
                BatchRealizationDetail::getUrl(['batch' => $batch->getKey()]),
                $batch,
            );
    }

    public function test_batch_realization_detail_shows_plan_and_actual_values(): void
    {
        [$batch, $product, $visor, $strap] = $this->realizationFixture();

        $this->get("/admin/batch-realization/{$batch->getKey()}")
            ->assertOk()
            ->assertSee($product->item_code)
            ->assertSee($visor->item_code)
            ->assertSee('Realisasi material')
            ->assertSee('Hasil produksi');

        $page = app(BatchRealizationDetail::class);
        $page->mount($batch->fresh());

        $outputs = collect($page->outputRealisations())->keyBy(fn (array $row): string => $row['item']->getKey());
        $this->assertSame(100.0, $outputs[$product->getKey()]['planned']);
        $this->assertSame(80.0, $outputs[$product->getKey()]['actual']);
        $this->assertSame(-20.0, $outputs[$product->getKey()]['difference']);
        $this->assertSame(80.0, $outputs[$product->getKey()]['percentage']);

        $materials = collect($page->materialRealisations())->keyBy(fn (array $row): string => $row['item']->getKey());
        $this->assertSame(100.0, $materials[$visor->getKey()]['required']);
        $this->assertSame(80.0, $materials[$visor->getKey()]['used']);
        $this->assertSame(20.0, $materials[$visor->getKey()]['remaining']);
        $this->assertSame(200.0, $materials[$strap->getKey()]['required']);
        $this->assertSame(160.0, $materials[$strap->getKey()]['used']);
    }

    public function test_batch_realization_detail_can_be_exported_to_csv(): void
    {
        [$batch, $product] = $this->realizationFixture();

        $page = app(BatchRealizationDetail::class);
        $page->mount($batch->fresh());

        $response = $page->exportCsv();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            'attachment; filename=realisasi-',
            (string) $response->headers->get('Content-Disposition'),
        );

        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString('Realisasi Produksi', $csv);
        $this->assertStringContainsString($product->item_code, $csv);
        $this->assertStringContainsString('Realisasi Material', $csv);
    }

    public function test_batch_can_be_started_and_finished_from_view_page(): void
    {
        $batch = ProductionBatch::factory()->create();

        Livewire::test(ViewProductionBatch::class, ['record' => $batch->getRouteKey()])
            ->callAction('start')
            ->assertHasNoActionErrors();

        $this->assertSame(ProductionBatch::STATUS_RUNNING, $batch->fresh()->status);
        $this->assertNotNull($batch->fresh()->started_at);

        Livewire::test(ViewProductionBatch::class, ['record' => $batch->getRouteKey()])
            ->callAction('finish')
            ->assertHasNoActionErrors();

        $this->assertSame(ProductionBatch::STATUS_COMPLETED, $batch->fresh()->status);
        $this->assertNotNull($batch->fresh()->finished_at);
    }

    public function test_batch_can_be_created_through_filament(): void
    {
        $materialWarehouse = Warehouse::factory()->create();
        $outputWarehouse = Warehouse::factory()->create();

        Livewire::test(CreateProductionBatch::class)
            ->fillForm([
                'batch_code' => 'BATCH-TEST-01',
                'material_warehouse_id' => $materialWarehouse->getKey(),
                'output_warehouse_id' => $outputWarehouse->getKey(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('production_batches', [
            'batch_code' => 'BATCH-TEST-01',
            'status' => ProductionBatch::STATUS_DRAFT,
            'material_warehouse_id' => $materialWarehouse->getKey(),
            'output_warehouse_id' => $outputWarehouse->getKey(),
        ]);
    }

    public function test_item_component_can_be_added_through_relation_manager(): void
    {
        $parent = Item::factory()->create();
        $component = Item::factory()->create();

        Livewire::test(ItemComponentsRelationManager::class, [
            'ownerRecord' => $parent,
            'pageClass' => EditItem::class,
        ])
            ->callTableAction(CreateAction::class, data: [
                'component_item_id' => $component->getKey(),
                'quantity' => 2,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('item_components', [
            'parent_item_id' => $parent->getKey(),
            'component_item_id' => $component->getKey(),
        ]);
    }

    public function test_adding_a_product_through_relation_manager_recalculates_requirements(): void
    {
        $product = Item::factory()->create();
        $component = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $product->getKey(),
            'component_item_id' => $component->getKey(),
            'quantity' => 3,
        ]);

        $batch = ProductionBatch::factory()->create();

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewProductionBatch::class,
        ])
            ->callTableAction(CreateAction::class, data: [
                'item_id' => $product->getKey(),
                'quantity_planned' => 10,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('production_batch_items', [
            'production_batch_id' => $batch->getKey(),
            'item_id' => $product->getKey(),
        ]);

        $material = $batch->materials()->where('item_id', $component->getKey())->firstOrFail();
        $this->assertSame(30.0, (float) $material->quantity_required);
    }

    public function test_raw_material_requirements_are_exploded_to_leaf_items(): void
    {
        $warehouse = Warehouse::factory()->create();

        $firstProduct = Item::factory()->create(['item_code' => 'PROD-1']);
        $secondProduct = Item::factory()->create(['item_code' => 'PROD-2']);
        $semi = Item::factory()->create(['item_code' => 'SEMI-1']);
        $firstRaw = Item::factory()->create(['item_code' => 'RAW-A']);
        $secondRaw = Item::factory()->create(['item_code' => 'RAW-B']);

        ItemComponent::factory()->create([
            'parent_item_id' => $firstProduct->getKey(),
            'component_item_id' => $semi->getKey(),
            'quantity' => 1,
        ]);
        ItemComponent::factory()->create([
            'parent_item_id' => $secondProduct->getKey(),
            'component_item_id' => $semi->getKey(),
            'quantity' => 1,
        ]);
        ItemComponent::factory()->create([
            'parent_item_id' => $semi->getKey(),
            'component_item_id' => $firstRaw->getKey(),
            'quantity' => 2,
        ]);
        ItemComponent::factory()->create([
            'parent_item_id' => $semi->getKey(),
            'component_item_id' => $secondRaw->getKey(),
            'quantity' => 0.5,
        ]);

        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $firstRaw->getKey(),
            'quantity_on_hand' => 20,
        ]);
        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $secondRaw->getKey(),
            'quantity_on_hand' => 100,
        ]);

        $batch = ProductionBatch::factory()->create([
            'material_warehouse_id' => $warehouse->getKey(),
        ]);

        ProductionBatchItem::factory()->create([
            'production_batch_id' => $batch->getKey(),
            'item_id' => $firstProduct->getKey(),
            'quantity_planned' => 10,
        ]);
        ProductionBatchItem::factory()->create([
            'production_batch_id' => $batch->getKey(),
            'item_id' => $secondProduct->getKey(),
            'quantity_planned' => 5,
        ]);

        $rows = app(BomExplosionService::class)
            ->rawMaterials($batch)
            ->keyBy(fn (array $row): string => $row['item']->item_code);

        $this->assertCount(2, $rows);
        $this->assertArrayNotHasKey('SEMI-1', $rows->all());

        $this->assertSame(30.0, $rows['RAW-A']['quantity_required']);
        $this->assertSame(20.0, $rows['RAW-A']['stock_on_hand']);
        $this->assertSame(10.0, $rows['RAW-A']['shortage_quantity']);

        $this->assertSame(7.5, $rows['RAW-B']['quantity_required']);
        $this->assertSame(0.0, $rows['RAW-B']['shortage_quantity']);
    }

    public function test_raw_materials_tab_lists_only_leaf_items(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Item::factory()->create(['item_code' => 'PROD-RAW']);
        $semi = Item::factory()->create(['item_code' => 'SEMI-RAW']);
        $raw = Item::factory()->create(['item_code' => 'RAW-LEAF']);

        ItemComponent::factory()->create([
            'parent_item_id' => $product->getKey(),
            'component_item_id' => $semi->getKey(),
            'quantity' => 1,
        ]);
        ItemComponent::factory()->create([
            'parent_item_id' => $semi->getKey(),
            'component_item_id' => $raw->getKey(),
            'quantity' => 1,
        ]);

        $batch = ProductionBatch::factory()->create([
            'material_warehouse_id' => $warehouse->getKey(),
        ]);
        ProductionBatchItem::factory()->create([
            'production_batch_id' => $batch->getKey(),
            'item_id' => $product->getKey(),
            'quantity_planned' => 10,
        ]);

        Livewire::test(RawMaterialsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewProductionBatch::class,
        ])
            ->assertSee('Rincian bahan baku')
            ->assertSee('RAW-LEAF')
            ->assertDontSee('SEMI-RAW');
    }

    public function test_material_requirements_mark_semi_finished_items(): void
    {
        $product = Item::factory()->create();
        $semi = Item::factory()->create();
        $raw = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $product->getKey(),
            'component_item_id' => $semi->getKey(),
            'quantity' => 1,
        ]);
        ItemComponent::factory()->create([
            'parent_item_id' => $semi->getKey(),
            'component_item_id' => $raw->getKey(),
            'quantity' => 1,
        ]);

        $batch = ProductionBatch::factory()->create();
        ProductionBatchItem::factory()->create([
            'production_batch_id' => $batch->getKey(),
            'item_id' => $product->getKey(),
            'quantity_planned' => 10,
        ]);

        app(BatchRequirementService::class)->recalculate($batch);

        Livewire::test(MaterialsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewProductionBatch::class,
        ])
            ->assertSee('1 komponen');
    }

    /**
     * Batch dengan 1 produk (rencana 100), 2 material (1x dan 2x per unit),
     * serta realisasi 80 hasil produksi dan pemakaian materialnya.
     *
     * @return array{0: ProductionBatch, 1: Item, 2: Item, 3: Item}
     */
    private function realizationFixture(): array
    {
        $product = Item::factory()->create();
        $visor = Item::factory()->create();
        $strap = Item::factory()->create();

        ItemComponent::factory()->create([
            'parent_item_id' => $product->getKey(),
            'component_item_id' => $visor->getKey(),
            'quantity' => 1,
        ]);
        ItemComponent::factory()->create([
            'parent_item_id' => $product->getKey(),
            'component_item_id' => $strap->getKey(),
            'quantity' => 2,
        ]);

        $materialWarehouse = Warehouse::factory()->create();
        $outputWarehouse = Warehouse::factory()->create();

        $batch = ProductionBatch::factory()->create([
            'material_warehouse_id' => $materialWarehouse->getKey(),
            'output_warehouse_id' => $outputWarehouse->getKey(),
        ]);

        ProductionBatchItem::factory()->create([
            'production_batch_id' => $batch->getKey(),
            'item_id' => $product->getKey(),
            'quantity_planned' => 100,
        ]);

        app(BatchRequirementService::class)->recalculate($batch);

        InventoryMovement::factory()->create([
            'type' => InventoryMovement::TYPE_IN_PRODUCTION,
            'item_id' => $product->getKey(),
            'warehouse_id' => $outputWarehouse->getKey(),
            'production_batch_id' => $batch->getKey(),
            'quantity' => 80,
        ]);

        InventoryMovement::factory()->create([
            'type' => InventoryMovement::TYPE_OUT_PRODUCTION,
            'item_id' => $visor->getKey(),
            'warehouse_id' => $materialWarehouse->getKey(),
            'production_batch_id' => $batch->getKey(),
            'quantity' => 80,
        ]);

        InventoryMovement::factory()->create([
            'type' => InventoryMovement::TYPE_OUT_PRODUCTION,
            'item_id' => $strap->getKey(),
            'warehouse_id' => $materialWarehouse->getKey(),
            'production_batch_id' => $batch->getKey(),
            'quantity' => 160,
        ]);

        return [$batch, $product, $visor, $strap];
    }
}
