<?php

namespace Tests\Feature;

use App\Filament\Resources\Deliveries\Pages\ViewDelivery;
use App\Models\Delivery;
use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DeliveryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeliveryPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_download_the_delivery_note_pdf(): void
    {
        $this->actingAs(User::factory()->create());
        [$delivery] = $this->makeDelivery();
        app(DeliveryService::class)->ship($delivery);

        $response = $this->get(route('filament.admin.deliveries.pdf', ['delivery' => $delivery]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    public function test_a_draft_delivery_has_no_delivery_note(): void
    {
        $this->actingAs(User::factory()->create());
        [$delivery] = $this->makeDelivery();

        $this->get(route('filament.admin.deliveries.pdf', ['delivery' => $delivery]))
            ->assertNotFound();
    }

    public function test_guest_is_redirected_to_the_panel_login(): void
    {
        [$delivery] = $this->makeDelivery();

        $this->get(route('filament.admin.deliveries.pdf', ['delivery' => $delivery]))
            ->assertRedirectContains('/admin/login');
    }

    public function test_the_document_renders_the_delivery_information(): void
    {
        [$delivery] = $this->makeDelivery();

        $html = view('pdf.delivery-note', ['delivery' => $this->loadDocumentRelations($delivery)])->render();

        $this->assertStringContainsString('Surat Jalan', $html);
        $this->assertStringContainsString((string) $delivery->code, $html);
        $this->assertStringContainsString('PT Tujuan Jaya', $html);
        $this->assertStringContainsString('Gudang Utama', $html);
        $this->assertStringContainsString('ITM-0001-TST', $html);
        $this->assertStringContainsString('Helmet Full Face', $html);
        $this->assertStringContainsString('PCS', $html);
        $this->assertStringContainsString('Catatan', $html);
        $this->assertStringContainsString('Kirim sebelum jam 12', $html);
    }

    public function test_the_document_leaves_the_signature_names_blank(): void
    {
        $shipper = User::factory()->create(['name' => 'Nama Pengirim Uji']);
        $this->actingAs($shipper);
        [$delivery] = $this->makeDelivery();
        app(DeliveryService::class)->ship($delivery);

        $html = view('pdf.delivery-note', ['delivery' => $this->loadDocumentRelations($delivery->refresh())])->render();

        $this->assertStringContainsString('Hormat Kami', $html);
        $this->assertStringContainsString('Penerima', $html);
        $this->assertStringNotContainsString('Nama Pengirim Uji', $html);
    }

    public function test_the_note_can_be_added_from_the_delivery_page(): void
    {
        $this->actingAs(User::factory()->create());
        [$delivery] = $this->makeDelivery();
        $delivery->update(['note' => null]);

        Livewire::test(ViewDelivery::class, ['record' => $delivery->getRouteKey()])
            ->callAction('editNote', data: ['note' => 'Barang dikemas dalam karton.'])
            ->assertHasNoActionErrors();

        $this->assertSame('Barang dikemas dalam karton.', $delivery->refresh()->note);
    }

    public function test_the_note_can_be_changed_from_the_delivery_page(): void
    {
        $this->actingAs(User::factory()->create());
        [$delivery] = $this->makeDelivery();

        Livewire::test(ViewDelivery::class, ['record' => $delivery->getRouteKey()])
            ->assertActionHasLabel('editNote', 'Ubah catatan')
            ->callAction('editNote', data: ['note' => 'Kirim setelah jam 13'])
            ->assertHasNoActionErrors();

        $this->assertSame('Kirim setelah jam 13', $delivery->refresh()->note);
    }

    public function test_a_standard_delivery_note_fits_on_one_a4_page(): void
    {
        [$delivery] = $this->makeDelivery();

        $pdf = Pdf::loadView('pdf.delivery-note', ['delivery' => $this->loadDocumentRelations($delivery)])
            ->setPaper('a4');
        $pdf->render();

        $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }

    public function test_a_long_delivery_note_flows_onto_additional_pages(): void
    {
        [$delivery] = $this->makeDelivery();

        $items = Item::factory()->count(25)->create();

        $delivery->lines()->createMany(
            $items->map(fn (Item $item): array => [
                'item_id' => $item->getKey(),
                'quantity' => 1,
            ])->all(),
        );

        $pdf = Pdf::loadView('pdf.delivery-note', ['delivery' => $this->loadDocumentRelations($delivery)])
            ->setPaper('a4');
        $pdf->render();

        $this->assertGreaterThan(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }

    public function test_the_print_action_is_only_visible_for_shipped_deliveries(): void
    {
        $this->actingAs(User::factory()->create());
        [$delivery] = $this->makeDelivery();

        Livewire::test(ViewDelivery::class, ['record' => $delivery->getRouteKey()])
            ->assertActionHidden('printDeliveryNote');

        app(DeliveryService::class)->ship($delivery);

        Livewire::test(ViewDelivery::class, ['record' => $delivery->refresh()->getRouteKey()])
            ->assertActionVisible('printDeliveryNote');
    }

    /**
     * @return array{0: Delivery, 1: Item}
     */
    private function makeDelivery(string $recipientName = 'PT Tujuan Jaya'): array
    {
        $warehouse = Warehouse::factory()->create(['name' => 'Gudang Utama']);
        $uom = Uom::factory()->create(['code' => 'PCS', 'name' => 'Pieces']);
        $item = Item::factory()->create([
            'item_code' => 'ITM-0001-TST',
            'item_name' => 'Helmet Full Face',
            'description' => 'Ukuran L',
            'base_uom_id' => $uom->getKey(),
            'is_stockable' => true,
            'has_variants' => false,
        ]);

        InventoryBalance::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'item_id' => $item->getKey(),
            'quantity_on_hand' => 20,
        ]);

        $delivery = Delivery::factory()->create([
            'warehouse_id' => $warehouse->getKey(),
            'recipient_name' => $recipientName,
            'delivery_date' => now()->toDateString(),
            'note' => 'Kirim sebelum jam 12',
        ]);

        $delivery->lines()->create([
            'item_id' => $item->getKey(),
            'quantity' => 4,
            'note' => 'Warna hitam',
        ]);

        return [$delivery, $item];
    }

    private function loadDocumentRelations(Delivery $delivery): Delivery
    {
        $delivery->load(['warehouse', 'lines.item.baseUom', 'shipper', 'creator']);

        return $delivery;
    }
}
