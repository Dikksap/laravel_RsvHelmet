<?php

namespace Tests\Feature;

use App\Filament\Resources\Deliveries\Pages\CreateDelivery;
use App\Filament\Resources\Deliveries\Pages\ListDeliveries;
use App\Filament\Resources\Deliveries\Pages\ViewDelivery;
use App\Filament\Resources\Deliveries\RelationManagers\LinesRelationManager;
use App\Models\Delivery;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DeliveryService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_delivery_creation_stays_draft_without_stock_movements(): void
    {
        $warehouse = Warehouse::factory()->create();

        $delivery = app(DeliveryService::class)->create([
            'warehouse_id' => $warehouse->getKey(),
            'recipient_name' => 'PT Contoh',
            'delivery_date' => now()->toDateString(),
            'note' => 'Kirim pagi',
        ]);

        $this->assertSame(Delivery::STATUS_DRAFT, $delivery->status);
        $this->assertSame('Kirim pagi', $delivery->note);
        $this->assertStringStartsWith('SJ-'.$delivery->created_at->format('Ymd'), (string) $delivery->code);
        $this->assertSame(0, InventoryMovement::query()->count());
        $this->assertDatabaseCount('delivery_lines', 0);
    }

    public function test_ship_records_outgoing_movement_and_reduces_stock(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create(['is_stockable' => true, 'has_variants' => false]);
        $balance = InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 10,
        ]);

        $delivery = Delivery::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'recipient_name' => 'CV Tujuan',
        ]);
        $delivery->lines()->create([
            'item_id' => $item->getKey(),
            'quantity' => 4,
        ]);

        $shipped = app(DeliveryService::class)->ship($delivery);

        $this->assertSame(Delivery::STATUS_SHIPPED, $shipped->status);
        $this->assertNotNull($shipped->shipped_at);
        $this->assertSame(auth()->id(), $shipped->shipped_by);
        $this->assertSame(6.0, (float) $balance->refresh()->quantity_on_hand);

        $movement = InventoryMovement::query()->sole();
        $this->assertSame(InventoryMovement::TYPE_OUT_DELIVERY, $movement->type);
        $this->assertTrue(InventoryMovement::isOutgoingType($movement->type));
        $this->assertSame($delivery->getKey(), $movement->delivery_id);
        $this->assertSame($warehouse->getKey(), $movement->warehouse_id);
        $this->assertSame($delivery->code, $movement->reference);
        $this->assertSame(4.0, (float) $movement->quantity);
    }

    public function test_ship_is_rejected_when_stock_is_insufficient(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create(['is_stockable' => true, 'has_variants' => false]);
        $balance = InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 2,
        ]);

        $delivery = Delivery::factory()->create(['warehouse_id' => $warehouse->getKey()]);
        $delivery->lines()->create([
            'item_id' => $item->getKey(),
            'quantity' => 5,
        ]);

        try {
            app(DeliveryService::class)->ship($delivery);
            $this->fail('Pengiriman seharusnya ditolak karena stok tidak mencukupi.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'Stok tidak mencukupi',
                (string) collect($exception->errors())->flatten()->first(),
            );
        }

        $this->assertSame(Delivery::STATUS_DRAFT, $delivery->refresh()->status);
        $this->assertSame(0, InventoryMovement::query()->count());
        $this->assertSame(2.0, (float) $balance->refresh()->quantity_on_hand);
    }

    public function test_ship_requires_at_least_one_line(): void
    {
        $delivery = Delivery::factory()->create();

        try {
            app(DeliveryService::class)->ship($delivery);
            $this->fail('Pengiriman tanpa barang seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Tambahkan minimal satu barang sebelum mengirim.',
                (string) collect($exception->errors())->flatten()->first(),
            );
        }

        $this->assertSame(Delivery::STATUS_DRAFT, $delivery->refresh()->status);
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_cancelling_shipped_delivery_restores_stock(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create(['is_stockable' => true, 'has_variants' => false]);
        $balance = InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 10,
        ]);

        $delivery = Delivery::factory()->create(['warehouse_id' => $warehouse->getKey()]);
        $delivery->lines()->create([
            'item_id' => $item->getKey(),
            'quantity' => 4,
        ]);

        $service = app(DeliveryService::class);
        $service->ship($delivery);
        $cancelled = $service->cancel($delivery->refresh());

        $this->assertSame(Delivery::STATUS_CANCELLED, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame(auth()->id(), $cancelled->cancelled_by);
        $this->assertSame(10.0, (float) $balance->refresh()->quantity_on_hand);
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_cancelling_draft_delivery_keeps_stock(): void
    {
        $delivery = Delivery::factory()->create();

        $cancelled = app(DeliveryService::class)->cancel($delivery);

        $this->assertSame(Delivery::STATUS_CANCELLED, $cancelled->status);
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_duplicate_item_line_is_rejected(): void
    {
        $delivery = Delivery::factory()->create();
        $item = Item::factory()->create();

        $delivery->lines()->create([
            'item_id' => $item->getKey(),
            'quantity' => 1,
        ]);

        $this->expectException(QueryException::class);

        $delivery->lines()->create([
            'item_id' => $item->getKey(),
            'quantity' => 2,
        ]);
    }

    public function test_admin_can_create_and_ship_delivery_through_filament(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create(['is_stockable' => true, 'has_variants' => false]);
        $balance = InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 8,
        ]);

        Livewire::test(CreateDelivery::class)
            ->fillForm([
                'warehouse_id' => $warehouse->getKey(),
                'recipient_name' => 'PT Contoh',
                'delivery_date' => now()->toDateString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $delivery = Delivery::query()->sole();
        $delivery->lines()->create([
            'item_id' => $item->getKey(),
            'quantity' => 3,
        ]);

        Livewire::test(ViewDelivery::class, ['record' => $delivery->getRouteKey()])
            ->assertActionVisible('ship')
            ->callAction('ship')
            ->assertHasNoActionErrors();

        $this->assertSame(Delivery::STATUS_SHIPPED, $delivery->refresh()->status);
        $this->assertSame(5.0, (float) $balance->refresh()->quantity_on_hand);
        $this->assertSame(1, InventoryMovement::query()->count());
    }

    public function test_delivery_list_page_renders_draft_records(): void
    {
        $deliveries = Delivery::factory()->count(2)->create();

        Livewire::test(ListDeliveries::class)
            ->assertOk()
            ->assertCanSeeTableRecords($deliveries);
    }

    public function test_lines_relation_manager_adds_stocked_item_to_draft(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create(['is_stockable' => true, 'has_variants' => false]);
        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 12,
        ]);

        $delivery = Delivery::factory()->create(['warehouse_id' => $warehouse->getKey()]);

        Livewire::test(LinesRelationManager::class, [
            'ownerRecord' => $delivery,
            'pageClass' => ViewDelivery::class,
        ])
            ->assertOk()
            ->callAction(TestAction::make('create')->table(), data: [
                'item_id' => $item->getKey(),
                'quantity' => 2,
            ])
            ->assertHasNoActionErrors();

        $line = $delivery->lines()->sole();
        $this->assertSame($item->getKey(), $line->item_id);
        $this->assertSame(2.0, (float) $line->quantity);
    }

    public function test_line_form_shows_available_stock_for_selected_item(): void
    {
        $warehouse = Warehouse::factory()->create(['name' => 'Gudang Utama']);
        $item = Item::factory()->create(['is_stockable' => true, 'has_variants' => false]);
        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 12,
        ]);

        $delivery = Delivery::factory()->create(['warehouse_id' => $warehouse->getKey()]);

        Livewire::test(LinesRelationManager::class, [
            'ownerRecord' => $delivery,
            'pageClass' => ViewDelivery::class,
        ])
            ->mountAction(TestAction::make('create')->table())
            ->assertMountedActionModalSee('Stok tersedia')
            ->setActionData(['item_id' => $item->getKey()])
            ->assertMountedActionModalSee('Stok tersedia')
            ->assertMountedActionModalSee('12')
            ->assertMountedActionModalSee('Gudang Utama');
    }

    public function test_lines_relation_manager_is_read_only_after_shipping(): void
    {
        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create(['is_stockable' => true, 'has_variants' => false]);
        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 6,
        ]);

        $delivery = Delivery::factory()->create(['warehouse_id' => $warehouse->getKey()]);
        $delivery->lines()->create([
            'item_id' => $item->getKey(),
            'quantity' => 2,
        ]);

        app(DeliveryService::class)->ship($delivery);

        Livewire::test(LinesRelationManager::class, [
            'ownerRecord' => $delivery->refresh(),
            'pageClass' => ViewDelivery::class,
        ])
            ->assertOk()
            ->assertActionHidden(TestAction::make('create')->table());
    }
}
