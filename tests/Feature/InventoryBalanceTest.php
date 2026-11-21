<?php

namespace Tests\Feature;

use App\Filament\Pages\InventoryBalanceSummary;
use App\Filament\Resources\InventoryBalances\InventoryBalanceResource;
use App\Filament\Resources\InventoryBalances\Pages\CreateInventoryBalance;
use App\Filament\Resources\InventoryBalances\Pages\EditInventoryBalance;
use App\Filament\Resources\Warehouses\Pages\CreateWarehouse;
use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_inventory_resources_are_in_the_warehouse_group(): void
    {
        $this->assertSame('Gudang', InventoryBalanceResource::getNavigationGroup());
        $this->assertNull(InventoryBalanceResource::getNavigationParentItem());

        $this->assertSame('Data Master', WarehouseResource::getNavigationGroup());
        $this->assertNull(WarehouseResource::getNavigationParentItem());
    }

    public function test_inventory_balance_form_uses_indonesian_stock_labels(): void
    {
        Livewire::test(CreateInventoryBalance::class)
            ->assertSee('Stok minimum')
            ->assertDontSee('Stok dipesan')
            ->assertDontSee('Pemesanan tertahan')
            ->assertDontSee('Stok tersedia');
    }

    public function test_inventory_balance_pages_render(): void
    {
        $balance = InventoryBalance::factory()->create();

        $urls = [
            '/admin/warehouses',
            '/admin/warehouses/create',
            "/admin/warehouses/{$balance->warehouse_id}/edit",
            '/admin/inventory-balances',
            '/admin/inventory-balances/create',
            "/admin/inventory-balances/{$balance->getKey()}/edit",
            InventoryBalanceSummary::getUrl(),
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_inventory_balance_summary_shows_stock_total_across_all_warehouses(): void
    {
        $item = Item::factory()->create();
        $firstWarehouse = Warehouse::factory()->create();
        $secondWarehouse = Warehouse::factory()->create();
        InventoryBalance::factory()->create([
            'item_id' => $item->getKey(),
            'warehouse_id' => $firstWarehouse->getKey(),
            'quantity_on_hand' => 25,
        ]);
        InventoryBalance::factory()->create([
            'item_id' => $item->getKey(),
            'warehouse_id' => $secondWarehouse->getKey(),
            'quantity_on_hand' => 15,
        ]);

        Livewire::test(InventoryBalanceSummary::class)
            ->assertSee('Total Stok Seluruh Gudang')
            ->assertTableColumnStateSet('total_stock_all_warehouses', '40.000', $item);
    }

    public function test_inventory_balance_summary_scopes_stock_total_to_selected_warehouse(): void
    {
        $item = Item::factory()->create();
        $otherItem = Item::factory()->create();
        $firstWarehouse = Warehouse::factory()->create();
        $secondWarehouse = Warehouse::factory()->create();

        InventoryBalance::factory()->create([
            'item_id' => $item->getKey(),
            'warehouse_id' => $firstWarehouse->getKey(),
            'quantity_on_hand' => 25,
        ]);
        InventoryBalance::factory()->create([
            'item_id' => $item->getKey(),
            'warehouse_id' => $secondWarehouse->getKey(),
            'quantity_on_hand' => 15,
        ]);
        InventoryBalance::factory()->create([
            'item_id' => $otherItem->getKey(),
            'warehouse_id' => $secondWarehouse->getKey(),
            'quantity_on_hand' => 5,
        ]);

        Livewire::test(InventoryBalanceSummary::class)
            ->filterTable('warehouse_id', $firstWarehouse->getKey())
            ->assertTableColumnStateSet('total_stock_all_warehouses', '25.000', $item)
            ->assertCanSeeTableRecords([$item])
            ->assertCanNotSeeTableRecords([$otherItem]);
    }

    public function test_inventory_balance_summary_filters_items_by_category_and_stock_status(): void
    {
        $category = ItemCategory::factory()->create();
        $stockedItem = Item::factory()->withCategory($category)->create();
        $outOfStockItem = Item::factory()->withCategory($category)->create();
        $otherItem = Item::factory()->create();

        InventoryBalance::factory()->create([
            'item_id' => $stockedItem->getKey(),
            'quantity_on_hand' => 10,
        ]);

        Livewire::test(InventoryBalanceSummary::class)
            ->filterTable('category_id', $category->getKey())
            ->assertCanSeeTableRecords([$stockedItem, $outOfStockItem])
            ->assertCanNotSeeTableRecords([$otherItem])
            ->filterTable('stock_status', 'out_of_stock')
            ->assertCanSeeTableRecords([$outOfStockItem])
            ->assertCanNotSeeTableRecords([$stockedItem, $otherItem]);
    }

    public function test_warehouses_can_be_created_through_filament(): void
    {
        Livewire::test(CreateWarehouse::class)
            ->fillForm([
                'code' => 'WH-MAIN',
                'name' => 'Main Warehouse',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('warehouses', [
            'code' => 'WH-MAIN',
            'name' => 'Main Warehouse',
        ]);
    }

    public function test_inventory_balances_can_be_created_through_filament(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create();

        Livewire::test(CreateInventoryBalance::class)
            ->fillForm([
                'warehouse_id' => $warehouse->getKey(),
                'item_id' => $item->getKey(),
                'quantity_on_hand' => 100,
                'minimum_quantity' => 10,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('inventory_balances', [
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 100,
        ]);
    }

    public function test_an_item_cannot_have_two_balances_in_the_same_warehouse(): void
    {
        $balance = InventoryBalance::factory()->create();

        Livewire::test(CreateInventoryBalance::class)
            ->fillForm([
                'warehouse_id' => $balance->warehouse_id,
                'item_id' => $balance->item_id,
                'quantity_on_hand' => 10,
            ])
            ->call('create')
            ->assertHasFormErrors(['item_id' => 'unique']);

        $this->assertSame(1, InventoryBalance::query()->count());
    }

    public function test_edit_form_displays_stock_without_trailing_zeros(): void
    {
        $balance = InventoryBalance::factory()->create([
            'quantity_on_hand' => 100,
            'minimum_quantity' => 10,
        ]);

        Livewire::test(EditInventoryBalance::class, ['record' => $balance->getRouteKey()])
            ->assertFormSet([
                'quantity_on_hand' => '100',
                'minimum_quantity' => '10',
            ]);
    }

    public function test_inventory_balance_can_be_edited_through_filament(): void
    {
        $balance = InventoryBalance::factory()->create([
            'quantity_on_hand' => 50,
        ]);

        Livewire::test(EditInventoryBalance::class, ['record' => $balance->getRouteKey()])
            ->fillForm([
                'quantity_on_hand' => 75,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('75.000', $balance->fresh()->quantity_on_hand);
    }

    public function test_stock_status_is_calculated(): void
    {
        $inStock = InventoryBalance::factory()->create([
            'quantity_on_hand' => 100,
            'minimum_quantity' => 50,
        ]);
        $lowStock = InventoryBalance::factory()->create([
            'quantity_on_hand' => 20,
            'minimum_quantity' => 50,
        ]);
        $outOfStock = InventoryBalance::factory()->create([
            'quantity_on_hand' => 0,
            'minimum_quantity' => 10,
        ]);

        $this->assertSame('ok', $inStock->stock_status);
        $this->assertSame('low', $lowStock->stock_status);
        $this->assertSame('out', $outOfStock->stock_status);
    }
}
