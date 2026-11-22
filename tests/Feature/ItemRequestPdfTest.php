<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\ItemRequest;
use App\Models\ItemRequestLine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ItemRequestPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_signed_url_returns_a_pdf(): void
    {
        $itemRequest = ItemRequest::factory()->create();
        ItemRequestLine::factory()->create([
            'item_request_id' => $itemRequest->getKey(),
            'item_id' => null,
            'requested_item_name' => 'Sarung tangan kulit',
        ]);
        $url = URL::temporarySignedRoute(
            'item-requests.pdf',
            now()->addHour(),
            ['itemRequest' => $itemRequest],
        );

        $response = $this->get($url);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_an_unsigned_request_is_forbidden(): void
    {
        $itemRequest = ItemRequest::factory()->create();

        $this->get(route('item-requests.pdf', $itemRequest))->assertForbidden();
    }

    public function test_the_pdf_document_renders_a_manual_item_name(): void
    {
        $division = Division::factory()->create(['name' => 'Produksi']);
        $itemRequest = ItemRequest::factory()->create([
            'division_id' => $division->getKey(),
            'requester_name' => 'Budi Santoso',
        ]);
        ItemRequestLine::factory()->create([
            'item_request_id' => $itemRequest->getKey(),
            'item_id' => null,
            'requested_item_name' => 'Sarung tangan kulit',
        ]);
        $itemRequest->load(['division', 'lines.item.baseUom']);

        $html = view('pdf.item-request', ['itemRequest' => $itemRequest])->render();

        $this->assertStringContainsString('Produksi', $html);
        $this->assertStringContainsString('Budi Santoso', $html);
        $this->assertStringContainsString('Sarung tangan kulit', $html);
    }

    public function test_a_standard_request_fits_on_one_a4_page(): void
    {
        $division = Division::factory()->create(['name' => 'Produksi']);
        $itemRequest = ItemRequest::factory()->create([
            'division_id' => $division->getKey(),
            'requester_name' => 'Budi Santoso',
            'note' => 'Untuk kebutuhan produksi',
        ]);
        ItemRequestLine::factory()->create([
            'item_request_id' => $itemRequest->getKey(),
            'item_id' => null,
            'requested_item_name' => 'Sarung tangan kulit',
            'quantity' => 2,
        ]);
        $itemRequest->load(['division', 'lines.item.baseUom']);
        $pdf = Pdf::loadView('pdf.item-request', ['itemRequest' => $itemRequest])->setPaper('a4');
        $pdf->render();

        $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }

    public function test_a_long_request_flows_onto_additional_pages(): void
    {
        $itemRequest = ItemRequest::factory()->create([
            'requester_name' => 'Budi Santoso',
            'note' => 'Untuk kebutuhan produksi',
        ]);

        foreach (range(1, 25) as $lineNumber) {
            ItemRequestLine::factory()->create([
                'item_request_id' => $itemRequest->getKey(),
                'item_id' => null,
                'requested_item_name' => "Barang manual {$lineNumber}",
                'quantity' => 1,
            ]);
        }

        $itemRequest->load(['division', 'lines.item.baseUom']);
        $pdf = Pdf::loadView('pdf.item-request', ['itemRequest' => $itemRequest])->setPaper('a4');
        $pdf->render();

        $this->assertGreaterThan(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }
}
