<div class="max-h-[75vh] overflow-auto rounded-xl border border-slate-200 bg-slate-100 p-2 sm:p-4" aria-label="Pratinjau dokumen permintaan barang">
    @include('item-requests.document', ['itemRequest' => $submittedRequest, 'mode' => 'preview'])
</div>
