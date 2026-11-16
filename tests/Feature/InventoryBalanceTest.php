<?php

namespace Tests\Feature;

use App\Filament\Resources\InventoryBalances\InventoryBalanceResource;
use App\Filament\Resources\InventoryBalances\Pages\CreateInventoryBalance;
use App\Filament\Resources\InventoryBalances\Pages\EditInventoryBalance;
use App\Filament\Resources\Warehouses\Pages\CreateWarehouse;
use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Models\InventoryBalance;
use App\Models\Item;
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
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
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
