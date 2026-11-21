<?php

namespace Tests\Feature;

use App\Filament\Pages\ItemRequestSettings;
use App\Filament\Resources\ItemRequests\ItemRequestResource;
use App\Filament\Resources\ItemRequests\Pages\EditItemRequest;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\ItemRequest;
use App\Models\ItemRequestLine;
use App\Models\ItemRequestSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ItemRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ItemRequestApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_badge_counts_only_pending_requests(): void
    {
        ItemRequest::factory()->count(2)->create(['status' => ItemRequest::STATUS_PENDING]);
        ItemRequest::factory()->create(['status' => ItemRequest::STATUS_COMPLETED]);
        ItemRequest::factory()->create(['status' => ItemRequest::STATUS_REJECTED]);

        $this->assertSame('2', ItemRequestResource::getNavigationBadge());
        $this->assertSame('warning', ItemRequestResource::getNavigationBadgeColor());
    }

    public function test_navigation_badge_is_empty_when_no_requests_are_pending(): void
    {
        ItemRequest::factory()->create(['status' => ItemRequest::STATUS_COMPLETED]);

        $this->assertNull(ItemRequestResource::getNavigationBadge());
    }

    public function test_approving_request_uses_configured_warehouses_and_transfers_stock(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $origin = Warehouse::factory()->create();
        $destination = Warehouse::factory()->create();
        $item = Item::factory()->create();

        ItemRequestSetting::current()->update([
            'warehouse_id' => $origin->getKey(),
            'destination_warehouse_id' => $destination->getKey(),
        ]);

        InventoryBalance::factory()->create([
            'warehouse_id' => $origin->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 10,
        ]);

        $itemRequest = ItemRequest::factory()->create(['status' => ItemRequest::STATUS_PENDING]);
        ItemRequestLine::factory()->create([
            'item_request_id' => $itemRequest->getKey(),
            'item_id' => $item->getKey(),
            'quantity' => 3,
        ]);

        Livewire::test(EditItemRequest::class, ['record' => $itemRequest->getRouteKey()])
            ->callAction('approve', data: [
                'note' => 'Disetujui untuk produksi',
            ])
            ->assertHasNoActionErrors();

        $itemRequest->refresh();

        $this->assertSame(ItemRequest::STATUS_COMPLETED, $itemRequest->status);
        $this->assertSame($origin->getKey(), $itemRequest->warehouse_id);
        $this->assertSame($destination->getKey(), $itemRequest->destination_warehouse_id);
        $this->assertSame($user->getKey(), $itemRequest->approved_by);
        $this->assertNotNull($itemRequest->approved_at);

        $this->assertSame(7.0, (float) InventoryBalance::query()
            ->where('warehouse_id', $origin->getKey())
            ->where('item_id', $item->getKey())
            ->value('quantity_on_hand'));

        $this->assertSame(3.0, (float) InventoryBalance::query()
            ->where('warehouse_id', $destination->getKey())
            ->where('item_id', $item->getKey())
            ->value('quantity_on_hand'));

        $movements = InventoryMovement::query()
            ->where('item_request_id', $itemRequest->getKey())
            ->get();

        $this->assertCount(2, $movements);
        $this->assertEqualsCanonicalizing(
            [InventoryMovement::TYPE_TRANSFER_OUT, InventoryMovement::TYPE_TRANSFER_IN],
            $movements->pluck('type')->all(),
        );
        $this->assertSame($itemRequest->code, $movements->first()->reference);
    }

    public function test_approval_fails_when_default_warehouses_are_not_configured(): void
    {
        $this->actingAs(User::factory()->create());

        $itemRequest = ItemRequest::factory()->create(['status' => ItemRequest::STATUS_PENDING]);
        ItemRequestLine::factory()->create([
            'item_request_id' => $itemRequest->getKey(),
            'item_id' => Item::factory()->create()->getKey(),
            'quantity' => 1,
        ]);

        Livewire::test(EditItemRequest::class, ['record' => $itemRequest->getRouteKey()])
            ->assertActionDisabled('approve');
    }

    public function test_approval_requires_all_lines_to_be_mapped(): void
    {
        $this->actingAs(User::factory()->create());

        $origin = Warehouse::factory()->create();
        $destination = Warehouse::factory()->create();

        ItemRequestSetting::current()->update([
            'warehouse_id' => $origin->getKey(),
            'destination_warehouse_id' => $destination->getKey(),
        ]);

        $itemRequest = ItemRequest::factory()->create(['status' => ItemRequest::STATUS_PENDING]);
        ItemRequestLine::factory()->create([
            'item_request_id' => $itemRequest->getKey(),
            'item_id' => null,
            'requested_item_name' => 'Sarung tangan kulit',
        ]);

        Livewire::test(EditItemRequest::class, ['record' => $itemRequest->getRouteKey()])
            ->callAction('approve')
            ->assertNotified('Permintaan gagal disetujui');

        $this->assertSame(ItemRequest::STATUS_PENDING, $itemRequest->fresh()->status);
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_approval_rejects_the_same_origin_and_destination_warehouse(): void
    {
        $this->actingAs(User::factory()->create());

        $warehouse = Warehouse::factory()->create();
        $item = Item::factory()->create();

        ItemRequestSetting::current()->update([
            'warehouse_id' => $warehouse->getKey(),
            'destination_warehouse_id' => $warehouse->getKey(),
        ]);

        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 10,
        ]);

        $itemRequest = ItemRequest::factory()->create(['status' => ItemRequest::STATUS_PENDING]);
        ItemRequestLine::factory()->create([
            'item_request_id' => $itemRequest->getKey(),
            'item_id' => $item->getKey(),
            'quantity' => 1,
        ]);

        Livewire::test(EditItemRequest::class, ['record' => $itemRequest->getRouteKey()])
            ->callAction('approve')
            ->assertNotified('Permintaan gagal disetujui');

        $this->assertSame(ItemRequest::STATUS_PENDING, $itemRequest->fresh()->status);
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_approval_fails_when_origin_stock_is_insufficient(): void
    {
        $this->actingAs(User::factory()->create());

        $origin = Warehouse::factory()->create();
        $destination = Warehouse::factory()->create();
        $item = Item::factory()->create();

        ItemRequestSetting::current()->update([
            'warehouse_id' => $origin->getKey(),
            'destination_warehouse_id' => $destination->getKey(),
        ]);

        InventoryBalance::factory()->create([
            'warehouse_id' => $origin->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 1,
        ]);

        $itemRequest = ItemRequest::factory()->create(['status' => ItemRequest::STATUS_PENDING]);
        ItemRequestLine::factory()->create([
            'item_request_id' => $itemRequest->getKey(),
            'item_id' => $item->getKey(),
            'quantity' => 5,
        ]);

        Livewire::test(EditItemRequest::class, ['record' => $itemRequest->getRouteKey()])
            ->callAction('approve')
            ->assertNotified('Permintaan gagal disetujui');

        $this->assertSame(ItemRequest::STATUS_PENDING, $itemRequest->fresh()->status);
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_rejecting_request_marks_it_rejected_without_changing_stock(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $item = Item::factory()->create();
        $warehouse = Warehouse::factory()->create();

        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 10,
        ]);

        $itemRequest = ItemRequest::factory()->create(['status' => ItemRequest::STATUS_PENDING]);
        ItemRequestLine::factory()->create([
            'item_request_id' => $itemRequest->getKey(),
            'item_id' => $item->getKey(),
            'quantity' => 2,
        ]);

        Livewire::test(EditItemRequest::class, ['record' => $itemRequest->getRouteKey()])
            ->callAction('reject', data: [
                'rejection_note' => 'Stok dialokasikan untuk kebutuhan lain.',
            ])
            ->assertHasNoActionErrors();

        $itemRequest->refresh();

        $this->assertSame(ItemRequest::STATUS_REJECTED, $itemRequest->status);
        $this->assertSame($user->getKey(), $itemRequest->rejected_by);
        $this->assertNotNull($itemRequest->rejected_at);
        $this->assertSame('Stok dialokasikan untuk kebutuhan lain.', $itemRequest->rejection_note);

        $this->assertSame(10.0, (float) InventoryBalance::query()
            ->where('warehouse_id', $warehouse->getKey())
            ->where('item_id', $item->getKey())
            ->value('quantity_on_hand'));
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_non_pending_request_cannot_be_approved(): void
    {
        $this->actingAs(User::factory()->create());

        $itemRequest = ItemRequest::factory()->create(['status' => ItemRequest::STATUS_COMPLETED]);

        $this->expectException(ValidationException::class);

        app(ItemRequestService::class)->approve($itemRequest, [
            'warehouse_id' => Warehouse::factory()->create()->getKey(),
            'destination_warehouse_id' => Warehouse::factory()->create()->getKey(),
        ]);
    }

    public function test_default_warehouses_can_be_saved_from_the_settings_page(): void
    {
        $this->actingAs(User::factory()->create());

        $origin = Warehouse::factory()->create();
        $destination = Warehouse::factory()->create();

        Livewire::test(ItemRequestSettings::class)
            ->fillForm([
                'warehouse_id' => $origin->getKey(),
                'destination_warehouse_id' => $destination->getKey(),
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Pengaturan disimpan');

        $settings = ItemRequestSetting::current();

        $this->assertSame($origin->getKey(), $settings->warehouse_id);
        $this->assertSame($destination->getKey(), $settings->destination_warehouse_id);
    }
}
