<?php

namespace App\Http\Controllers;

use App\Models\ItemRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class ItemRequestPdfController extends Controller
{
    public function __invoke(ItemRequest $itemRequest): Response
    {
        $itemRequest->loadMissing(['division', 'lines.item.baseUom']);

        return Pdf::loadView('pdf.item-request', ['itemRequest' => $itemRequest])
            ->setPaper('a4')
            ->stream("Permintaan-{$itemRequest->code}.pdf");
    }
}
