<?php

namespace Tests\Feature;

use App\Filament\Resources\ItemRequests\Pages\EditItemRequest;
use App\Filament\Resources\ItemRequests\RelationManagers\LinesRelationManager;
use App\Livewire\ItemRequestForm;
use App\Models\Division;
use App\Models\Item;
use App\Models\ItemRequest;
use App\Models\ItemRequestLine;
use App\Models\User;
use Filament\Actions\EditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ItemRequestFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_form_renders_for_guests(): void
    {
        Division::factory()->create(['name' => 'Produksi']);

        $response = $this->get('/permintaan-barang');

        $response->assertOk();
        $response->assertSee('Formulir Permintaan Barang');
        $response->assertSee('Tambah barang');
    }

    public function test_request_with_multiple_items_is_saved(): void
    {
        $division = Division::factory()->create(['name' => 'Produksi']);
        $firstItem = Item::factory()->create(['item_name' => 'Helmet Full Face']);
        $secondItem = Item::factory()->create(['item_name' => 'Helmet Half Face']);

        Livewire::test(ItemRequestForm::class)
            ->set('requester_name', 'Budi Santoso')
            ->set('division_id', (string) $division->getKey())
            ->set('note', 'Untuk kebutuhan produksi')
            ->set('lines', [
                ['key' => 1, 'item_id' => $firstItem->getKey(), 'quantity' => '2.5', 'note' => 'Warna hitam'],
                ['key' => 2, 'item_id' => $secondItem->getKey(), 'quantity' => '4', 'note' => ''],
            ])
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true)
            ->assertSee('Budi Santoso')
            ->assertSee('Produksi')
            ->assertSee('Helmet Full Face')
            ->assertSee($firstItem->baseUom->code)
            ->assertSee('Bagikan ke WhatsApp')
            ->assertSee('Unduh PDF')
            ->call('createAnother')
            ->assertSet('submitted', false)
            ->assertSet('submittedId', null)
            ->assertDontSee('Bagikan ke WhatsApp');

        $itemRequest = ItemRequest::query()->sole();

        $this->assertSame('Budi Santoso', $itemRequest->requester_name);
        $this->assertSame('Untuk kebutuhan produksi', $itemRequest->note);
        $this->assertTrue($itemRequest->division->is($division));
        $this->assertSame(ItemRequest::STATUS_PENDING, $itemRequest->status);
        $this->assertMatchesRegularExpression('/^PR-\d{8}-\d{4}$/', $itemRequest->code);

        $this->assertCount(2, $itemRequest->lines);

        $firstLine = $itemRequest->lines->firstWhere('item_id', $firstItem->getKey());
        $this->assertSame('2.500', $firstLine->quantity);
        $this->assertSame('Warna hitam', $firstLine->note);

        $secondLine = $itemRequest->lines->firstWhere('item_id', $secondItem->getKey());
        $this->assertSame('4.000', $secondLine->quantity);
        $this->assertNull($secondLine->note);
    }

    public function test_request_requires_requester_division_and_items(): void
    {
        Livewire::test(ItemRequestForm::class)
            ->call('submit')
            ->assertHasErrors([
                'requester_name',
                'division_id',
                'lines.0.item_query',
                'lines.0.quantity',
            ]);

        $this->assertSame(0, ItemRequest::query()->count());
    }

    public function test_request_rejects_an_inactive_division(): void
    {
        $division = Division::factory()->create(['is_active' => false]);
        $item = Item::factory()->create();

        Livewire::test(ItemRequestForm::class)
            ->set('requester_name', 'Budi Santoso')
            ->set('division_id', (string) $division->getKey())
            ->set('lines', [
                ['key' => 1, 'item_id' => $item->getKey(), 'quantity' => '1', 'note' => ''],
            ])
            ->call('submit')
            ->assertHasErrors(['division_id']);
    }

    public function test_request_rejects_duplicate_items(): void
    {
        $division = Division::factory()->create();
        $item = Item::factory()->create();

        Livewire::test(ItemRequestForm::class)
            ->set('requester_name', 'Budi Santoso')
            ->set('division_id', (string) $division->getKey())
            ->set('lines', [
                ['key' => 1, 'item_id' => $item->getKey(), 'item_query' => '', 'quantity' => '1', 'note' => ''],
                ['key' => 2, 'item_id' => $item->getKey(), 'item_query' => '', 'quantity' => '2', 'note' => ''],
            ])
            ->call('submit')
            ->assertHasErrors(['lines.1.item_query']);

        $this->assertSame(0, ItemRequest::query()->count());
    }

    public function test_request_rejects_an_inactive_item(): void
    {
        $division = Division::factory()->create();
        $item = Item::factory()->create(['is_active' => false]);

        Livewire::test(ItemRequestForm::class)
            ->set('requester_name', 'Budi Santoso')
            ->set('division_id', (string) $division->getKey())
            ->set('lines', [
                ['key' => 1, 'item_id' => $item->getKey(), 'quantity' => '1', 'note' => ''],
            ])
            ->call('submit')
            ->assertHasErrors(['lines.0.item_id']);
    }

    public function test_request_rejects_a_non_positive_quantity(): void
    {
        $division = Division::factory()->create();
        $item = Item::factory()->create();

        Livewire::test(ItemRequestForm::class)
            ->set('requester_name', 'Budi Santoso')
            ->set('division_id', (string) $division->getKey())
            ->set('lines', [
                ['key' => 1, 'item_id' => $item->getKey(), 'quantity' => '0', 'note' => ''],
            ])
            ->call('submit')
            ->assertHasErrors(['lines.0.quantity']);
    }

    public function test_request_can_use_a_manual_item_name(): void
    {
        $division = Division::factory()->create();

        Livewire::test(ItemRequestForm::class)
            ->set('requester_name', 'Budi Santoso')
            ->set('division_id', (string) $division->getKey())
            ->set('lines', [
                ['key' => 1, 'item_id' => '', 'item_query' => 'Sarung tangan kulit', 'quantity' => '2', 'note' => ''],
            ])
            ->call('submit')
            ->assertHasNoErrors();

        $line = ItemRequest::query()->sole()->lines()->sole();

        $this->assertNull($line->item_id);
        $this->assertSame('Sarung tangan kulit', $line->requested_item_name);
    }

    public function test_typing_an_existing_item_name_without_selecting_it_maps_automatically(): void
    {
        $division = Division::factory()->create();
        $item = Item::factory()->create(['item_name' => 'Helmet Full Face']);

        Livewire::test(ItemRequestForm::class)
            ->set('requester_name', 'Budi Santoso')
            ->set('division_id', (string) $division->getKey())
            ->set('lines', [
                ['key' => 1, 'item_id' => '', 'item_query' => 'helmet full face', 'quantity' => '1', 'note' => ''],
            ])
            ->call('submit')
            ->assertHasNoErrors();

        $line = ItemRequest::query()->sole()->lines()->sole();

        $this->assertSame($item->getKey(), $line->item_id);
        $this->assertNull($line->requested_item_name);
    }

    public function test_request_rejects_duplicate_manual_item_names(): void
    {
        $division = Division::factory()->create();

        Livewire::test(ItemRequestForm::class)
            ->set('requester_name', 'Budi Santoso')
            ->set('division_id', (string) $division->getKey())
            ->set('lines', [
                ['key' => 1, 'item_id' => '', 'item_query' => 'Sarung tangan kulit', 'quantity' => '1', 'note' => ''],
                ['key' => 2, 'item_id' => '', 'item_query' => 'SARUNG TANGAN KULIT', 'quantity' => '2', 'note' => ''],
            ])
            ->call('submit')
            ->assertHasErrors(['lines.1.item_query']);

        $this->assertSame(0, ItemRequest::query()->count());
    }

    public function test_item_search_filters_the_dropdown_results(): void
    {
        Item::factory()->create(['item_name' => 'Helmet Full Face']);
        Item::factory()->create(['item_name' => 'Sarung Tangan']);

        Livewire::test(ItemRequestForm::class)
            ->set('lines.0.item_query', 'Helmet')
            ->assertSee('Helmet Full Face')
            ->assertDontSee('Sarung Tangan');
    }

    public function test_admin_can_map_a_manual_item_to_an_existing_item(): void
    {
        $this->actingAs(User::factory()->create());

        $itemRequest = ItemRequest::factory()->create();
        $line = ItemRequestLine::factory()->create([
            'item_request_id' => $itemRequest->getKey(),
            'item_id' => null,
            'requested_item_name' => 'Sarung tangan kulit',
        ]);

        $item = Item::factory()->create(['item_name' => 'Sarung Tangan Kulit']);

        Livewire::test(LinesRelationManager::class, [
            'ownerRecord' => $itemRequest,
            'pageClass' => EditItemRequest::class,
        ])
            ->callTableAction(EditAction::class, $line, data: [
                'item_id' => $item->getKey(),
            ])
            ->assertHasNoTableActionErrors();

        $line->refresh();

        $this->assertSame($item->getKey(), $line->item_id);
        $this->assertTrue($line->is_mapped);
    }

    public function test_admin_can_view_a_request_but_cannot_change_status_manually(): void
    {
        $this->actingAs(User::factory()->create());

        $itemRequest = ItemRequest::factory()->create();

        $this->get('/admin/item-requests')->assertOk();
        $this->get("/admin/item-requests/{$itemRequest->getKey()}/edit")->assertOk();

        Livewire::test(EditItemRequest::class, ['record' => $itemRequest->getKey()])
            ->fillForm([
                'status' => ItemRequest::STATUS_PROCESSING,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(ItemRequest::STATUS_PENDING, $itemRequest->fresh()->status);
    }
}
