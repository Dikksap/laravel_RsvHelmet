<?php

namespace Tests\Feature;

use App\Filament\Pages\WarehouseStock;
use App\Filament\Pages\WarehouseStockDetail;
use App\Filament\Resources\InventoryBalances\Pages\CreateInventoryBalance;
use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WarehouseStockPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_warehouse_stock_picker_lists_warehouses_and_links_to_their_stock(): void
    {
        $warehouse = Warehouse::factory()->create([
            'name' => 'Gudang Utama',
            'code' => 'WH-UTAMA',
        ]);
        Warehouse::factory()->create(['name' => 'Gudang Cadangan']);

        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'quantity_on_hand' => 80,
            'minimum_quantity' => 10,
        ]);
        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'quantity_on_hand' => 5,
            'minimum_quantity' => 10,
        ]);

        Livewire::test(WarehouseStock::class)
            ->assertOk()
            ->assertSee('Gudang Utama')
            ->assertSee('WH-UTAMA')
            ->assertSee('Gudang Cadangan')
            ->assertSee('1 menipis')
            ->assertSee(WarehouseStockDetail::getUrl(['warehouse' => $warehouse]), escape: false);
    }

    public function test_warehouse_stock_pages_render(): void
    {
        $warehouse = Warehouse::factory()->create();
        InventoryBalance::factory()->create(['warehouse_id' => $warehouse->getKey()]);

        $this->get(WarehouseStock::getUrl())->assertOk();
        $this->get(WarehouseStockDetail::getUrl(['warehouse' => $warehouse]))->assertOk();
    }

    public function test_warehouse_stock_detail_only_lists_stock_of_the_selected_warehouse(): void
    {
        $firstWarehouse = Warehouse::factory()->create(['name' => 'Gudang Satu']);
        $secondWarehouse = Warehouse::factory()->create(['name' => 'Gudang Dua']);

        $selected = InventoryBalance::factory()->create([
            'warehouse_id' => $firstWarehouse->getKey(),
            'quantity_on_hand' => 12,
        ]);
        $other = InventoryBalance::factory()->create([
            'warehouse_id' => $secondWarehouse->getKey(),
            'quantity_on_hand' => 4,
        ]);

        Livewire::test(WarehouseStockDetail::class, ['warehouse' => $firstWarehouse])
            ->assertOk()
            ->assertSee('Gudang Satu')
            ->assertCanSeeTableRecords([$selected])
            ->assertCanNotSeeTableRecords([$other])
            ->assertTableColumnStateSet('quantity_on_hand', '12.000', $selected);
    }

    public function test_warehouse_stock_detail_filters_rows_by_stock_status(): void
    {
        $warehouse = Warehouse::factory()->create();

        $safe = InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'quantity_on_hand' => 100,
            'minimum_quantity' => 10,
        ]);
        $low = InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'quantity_on_hand' => 5,
            'minimum_quantity' => 10,
        ]);
        $out = InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'quantity_on_hand' => 0,
            'minimum_quantity' => 5,
        ]);

        Livewire::test(WarehouseStockDetail::class, ['warehouse' => $warehouse])
            ->filterTable('stock_status', 'low')
            ->assertCanSeeTableRecords([$low])
            ->assertCanNotSeeTableRecords([$safe, $out]);
    }

    public function test_warehouse_stock_detail_summarises_the_selected_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create();
        $otherWarehouse = Warehouse::factory()->create();

        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'quantity_on_hand' => 100,
            'minimum_quantity' => 10,
        ]);
        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'quantity_on_hand' => 5,
            'minimum_quantity' => 10,
        ]);
        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'quantity_on_hand' => 0,
            'minimum_quantity' => 5,
        ]);
        InventoryBalance::factory()->create([
            'warehouse_id' => $otherWarehouse->getKey(),
            'quantity_on_hand' => 50,
        ]);

        $summary = Livewire::test(WarehouseStockDetail::class, ['warehouse' => $warehouse])
            ->instance()
            ->getSummary();

        $this->assertSame(3, $summary['total_items']);
        $this->assertSame(105.0, $summary['total_quantity']);
        $this->assertSame(1, $summary['low_stock_count']);
        $this->assertSame(1, $summary['out_of_stock_count']);
    }

    public function test_stock_can_be_created_for_the_warehouse_selected_on_the_detail_page(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create();

        Livewire::withQueryParams(['warehouse_id' => $warehouse->getKey()])
            ->test(CreateInventoryBalance::class)
            ->assertFormSet(['warehouse_id' => $warehouse->getKey()])
            ->fillForm([
                'item_id' => $item->getKey(),
                'quantity_on_hand' => 25,
                'minimum_quantity' => 5,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(WarehouseStockDetail::getUrl(['warehouse' => $warehouse]));

        $this->assertDatabaseHas('inventory_balances', [
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 25,
        ]);
    }
}
