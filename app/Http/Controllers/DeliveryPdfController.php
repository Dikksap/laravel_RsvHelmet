<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class DeliveryPdfController extends Controller
{
    public function __invoke(Delivery $delivery): Response
    {
        abort_unless($delivery->isShipped(), 404);

        $delivery->loadMissing(['warehouse', 'lines.item.baseUom', 'shipper', 'creator']);

        return Pdf::loadView('pdf.delivery-note', ['delivery' => $delivery])
            ->setPaper('a4')
            ->stream("Surat-Jalan-{$delivery->code}.pdf");
    }
}
