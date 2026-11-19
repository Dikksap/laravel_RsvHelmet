<?php

namespace Tests\Feature;

use App\Filament\Resources\InventoryBalances\Pages\ListInventoryBalances;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class MassStockTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_multiple_items_can_be_transferred_from_one_warehouse(): void
    {
        $origin = Warehouse::factory()->create();
        $destination = Warehouse::factory()->create();

        $first = InventoryBalance::factory()->create([
            'warehouse_id' => $origin->getKey(),
            'quantity_on_hand' => 50,
        ]);
        $second = InventoryBalance::factory()->create([
            'warehouse_id' => $origin->getKey(),
            'quantity_on_hand' => 20,
        ]);

        $transfers = app(StockMovementService::class)->transferMany([
            'warehouse_id' => $origin->getKey(),
            'destination_warehouse_id' => $destination->getKey(),
            'reference' => 'PINDAH-UJI',
            'items' => [
                ['item_id' => $first->item_id, 'quantity' => 30],
                ['item_id' => $second->item_id, 'quantity' => 5],
            ],
        ]);

        $this->assertCount(2, $transfers);

        $this->assertSame('PINDAH-UJI', $transfers[0][0]->reference);
        $this->assertSame('PINDAH-UJI', $transfers[1][0]->reference);
        $this->assertNotSame($transfers[0][0]->transfer_group_id, $transfers[1][0]->transfer_group_id);

        $this->assertSame(20.0, $this->stockOf($origin, $first->item_id));
        $this->assertSame(30.0, $this->stockOf($destination, $first->item_id));
        $this->assertSame(15.0, $this->stockOf($origin, $second->item_id));
        $this->assertSame(5.0, $this->stockOf($destination, $second->item_id));
        $this->assertSame(4, InventoryMovement::query()->count());
    }

    public function test_mass_transfer_rolls_back_every_item_when_one_item_is_short(): void
    {
        $origin = Warehouse::factory()->create();
        $destination = Warehouse::factory()->create();

        $first = InventoryBalance::factory()->create([
            'warehouse_id' => $origin->getKey(),
            'quantity_on_hand' => 50,
        ]);
        $second = InventoryBalance::factory()->create([
            'warehouse_id' => $origin->getKey(),
            'quantity_on_hand' => 3,
        ]);

        try {
            app(StockMovementService::class)->transferMany([
                'warehouse_id' => $origin->getKey(),
                'destination_warehouse_id' => $destination->getKey(),
                'items' => [
                    ['item_id' => $first->item_id, 'quantity' => 10],
                    ['item_id' => $second->item_id, 'quantity' => 5],
                ],
            ]);

            $this->fail('Transfer massal seharusnya gagal ketika salah satu barang kurang.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'Stok tidak mencukupi',
                collect($exception->errors())->flatten()->first(),
            );
        }

        $this->assertSame(50.0, $this->stockOf($origin, $first->item_id));
        $this->assertSame(3.0, $this->stockOf($origin, $second->item_id));
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_mass_transfer_requires_a_different_destination_warehouse(): void
    {
        $origin = Warehouse::factory()->create();
        $balance = InventoryBalance::factory()->create([
            'warehouse_id' => $origin->getKey(),
            'quantity_on_hand' => 10,
        ]);

        $this->expectException(ValidationException::class);

        app(StockMovementService::class)->transferMany([
            'warehouse_id' => $origin->getKey(),
            'destination_warehouse_id' => $origin->getKey(),
            'items' => [
                ['item_id' => $balance->item_id, 'quantity' => 5],
            ],
        ]);
    }

    public function test_stock_can_be_transferred_in_bulk_through_filament(): void
    {
        $origin = Warehouse::factory()->create();
        $destination = Warehouse::factory()->create();

        $first = InventoryBalance::factory()->create([
            'warehouse_id' => $origin->getKey(),
            'quantity_on_hand' => 40,
        ]);
        $second = InventoryBalance::factory()->create([
            'warehouse_id' => $origin->getKey(),
            'quantity_on_hand' => 10,
        ]);

        Livewire::test(ListInventoryBalances::class)
            ->callTableBulkAction('transfer', [$first, $second], [
                'destination_warehouse_id' => $destination->getKey(),
                'occurred_at' => now(),
                'reference' => 'PINDAH-FILAMENT',
                'items' => [
                    ['balance_id' => $first->getKey(), 'on_hand' => 40, 'quantity' => 25],
                    ['balance_id' => $second->getKey(), 'on_hand' => 10, 'quantity' => 10],
                ],
            ])
            ->assertHasNoFormErrors()
            ->assertNotified('Stok berhasil dipindahkan');

        $this->assertSame(15.0, $this->stockOf($origin, $first->item_id));
        $this->assertSame(25.0, $this->stockOf($destination, $first->item_id));
        $this->assertSame(0.0, $this->stockOf($origin, $second->item_id));
        $this->assertSame(10.0, $this->stockOf($destination, $second->item_id));

        $this->assertSame(4, InventoryMovement::query()->where('reference', 'PINDAH-FILAMENT')->count());
    }

    public function test_filament_bulk_transfer_groups_items_from_different_origin_warehouses(): void
    {
        $firstOrigin = Warehouse::factory()->create();
        $secondOrigin = Warehouse::factory()->create();
        $destination = Warehouse::factory()->create();

        $first = InventoryBalance::factory()->create([
            'warehouse_id' => $firstOrigin->getKey(),
            'quantity_on_hand' => 30,
        ]);
        $second = InventoryBalance::factory()->create([
            'warehouse_id' => $secondOrigin->getKey(),
            'quantity_on_hand' => 15,
        ]);

        Livewire::test(ListInventoryBalances::class)
            ->callTableBulkAction('transfer', [$first, $second], [
                'destination_warehouse_id' => $destination->getKey(),
                'occurred_at' => now(),
                'reference' => 'PINDAH-BANYAK-GUDANG',
                'items' => [
                    ['balance_id' => $first->getKey(), 'on_hand' => 30, 'quantity' => 20],
                    ['balance_id' => $second->getKey(), 'on_hand' => 15, 'quantity' => 10],
                ],
            ])
            ->assertHasNoFormErrors()
            ->assertNotified('Stok berhasil dipindahkan');

        $this->assertSame(10.0, $this->stockOf($firstOrigin, $first->item_id));
        $this->assertSame(20.0, $this->stockOf($destination, $first->item_id));
        $this->assertSame(5.0, $this->stockOf($secondOrigin, $second->item_id));
        $this->assertSame(10.0, $this->stockOf($destination, $second->item_id));

        $movements = InventoryMovement::query()
            ->where('reference', 'PINDAH-BANYAK-GUDANG')
            ->get();

        $this->assertCount(4, $movements);
        $this->assertCount(2, $movements->pluck('transfer_group_id')->unique());
    }

    public function test_filament_bulk_transfer_rejects_a_destination_that_is_also_an_origin(): void
    {
        $origin = Warehouse::factory()->create();
        $balance = InventoryBalance::factory()->create([
            'warehouse_id' => $origin->getKey(),
            'quantity_on_hand' => 10,
        ]);

        Livewire::test(ListInventoryBalances::class)
            ->callTableBulkAction('transfer', [$balance], [
                'destination_warehouse_id' => $origin->getKey(),
                'occurred_at' => now(),
                'items' => [
                    ['balance_id' => $balance->getKey(), 'on_hand' => 10, 'quantity' => 5],
                ],
            ])
            ->assertHasNoFormErrors()
            ->assertNotified('Pindah gudang gagal');

        $this->assertSame(10.0, $this->stockOf($origin, $balance->item_id));
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    private function stockOf(Warehouse $warehouse, string $itemId): float
    {
        return (float) InventoryBalance::query()
            ->where('warehouse_id', $warehouse->getKey())
            ->where('item_id', $itemId)
            ->value('quantity_on_hand');
    }
}
