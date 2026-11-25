@php
    $itemLines = $itemRequest->lines->sortBy('id')->values();
    $rowCount = max(10, $itemLines->count());
@endphp

<style>
    .item-request-document {
        color: #1f2937;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 10pt;
    }

    .item-request-document *,
    .item-request-document *::before,
    .item-request-document *::after {
        box-sizing: border-box;
    }

    .item-request-toolbar {
        margin: 0;
        padding: 10px;
        border-bottom: 1px solid #d1d5db;
        background: #fff;
        text-align: center;
    }

    .item-request-toolbar button {
        padding: 8px 16px;
        border: 0;
        border-radius: 6px;
        background: #e30613;
        color: #fff;
        font-weight: 700;
        cursor: pointer;
    }

    .item-request-paper {
        width: 210mm;
        min-height: 297mm;
        margin: 12mm auto;
        padding: 14mm 16mm;
        background: #fff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.14);
    }

    .item-request-header,
    .item-request-info,
    .item-request-items,
    .item-request-signatures {
        width: 100%;
        border-collapse: collapse;
    }

    .item-request-header {
        margin-bottom: 4mm;
    }

    .item-request-header td {
        vertical-align: bottom;
    }

    .item-request-brand {
        color: #0b0b0b;
        font-size: 26pt;
        font-weight: 900;
        line-height: 1;
    }

    .item-request-tagline {
        margin-top: 1mm;
        color: #0b0b0b;
        font-size: 8pt;
        font-weight: 700;
        letter-spacing: 0.3em;
        text-transform: uppercase;
    }

    .item-request-description {
        margin-top: 1mm;
        color: #6b7280;
        font-size: 7.5pt;
    }

    .item-request-title {
        text-align: right;
    }

    .item-request-title strong,
    .item-request-title span {
        display: block;
    }

    .item-request-title strong {
        color: #0b0b0b;
        font-size: 11pt;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .item-request-title span {
        margin-top: 1mm;
        color: #e30613;
        font-family: monospace;
        font-size: 10pt;
        font-weight: 700;
    }

    .item-request-red-line {
        height: 2.5mm;
        margin-bottom: 6mm;
        background: #e30613;
    }

    .item-request-section-title {
        margin: 0 0 2mm;
        color: #0b0b0b;
        font-size: 9.5pt;
        font-weight: 700;
        letter-spacing: 0.18em;
        text-transform: uppercase;
    }

    .item-request-info-wrap {
        margin-bottom: 6mm;
        padding: 3mm 4mm;
        border: 1px solid #d7e0ee;
        background: #f8fafc;
    }

    .item-request-info td {
        height: 7mm;
        vertical-align: middle;
    }

    .item-request-info td:nth-child(odd) {
        width: 18%;
        color: #6b7280;
    }

    .item-request-info td:nth-child(even) {
        width: 32%;
        font-weight: 700;
    }

    .item-request-items {
        margin-bottom: 6mm;
        table-layout: fixed;
        font-size: 9pt;
    }

    .item-request-items tr,
    .item-request-reason,
    .item-request-signatures {
        page-break-inside: avoid;
    }

    .item-request-items th {
        padding: 2mm;
        border: 1px solid #0b0b0b;
        border-bottom: 1.5mm solid #e30613;
        background: #0b0b0b;
        color: #fff;
        text-align: center;
    }

    .item-request-items td {
        height: 8mm;
        padding: 1.5mm 2mm;
        border: 1px solid #d7e0ee;
        overflow-wrap: anywhere;
    }

    .item-request-items tbody tr:nth-child(even) {
        background: #f8fafc;
    }

    .item-request-col-no { width: 6%; }
    .item-request-col-name { width: 35%; }
    .item-request-col-spec { width: 31%; }
    .item-request-col-qty { width: 12%; }
    .item-request-col-unit { width: 16%; }

    .item-request-items td.item-request-no {
        color: #e30613;
        font-weight: 700;
        text-align: center;
    }

    .item-request-reason {
        min-height: 20mm;
        margin-bottom: 10mm;
        padding: 3mm 4mm;
        border: 1px solid #d7e0ee;
    }

    .item-request-signatures {
        table-layout: fixed;
        text-align: center;
        font-size: 9pt;
    }

    .item-request-signature {
        padding: 0 3mm;
    }

    .item-request-signature-title {
        min-height: 19mm;
        white-space: pre-line;
    }

    .item-request-signature-name {
        padding-top: 1.5mm;
        border-top: 1px solid #1f2937;
        font-weight: 700;
    }

    .item-request-blank {
        color: #6b7280;
    }

    @page {
        size: A4;
        margin: 0;
    }

    .item-request-document--pdf .item-request-paper {
        box-sizing: content-box;
        width: 178mm;
        margin: 0;
        min-height: 0;
        box-shadow: none;
    }

    .item-request-document--pdf .item-request-items td {
        height: 4.5mm;
    }

    .item-request-document--pdf .item-request-reason {
        min-height: 14mm;
    }

    @media screen and (max-width: 640px) {
        .item-request-document--preview .item-request-paper {
            zoom: 0.6;
        }
    }

    @media screen and (max-width: 480px) {
        .item-request-document--preview .item-request-paper {
            zoom: 0.45;
        }
    }

    @media print {
        body * {
            visibility: hidden !important;
        }

        .item-request-document--preview,
        .item-request-document--preview * {
            visibility: visible !important;
        }

        .item-request-document--preview {
            position: fixed;
            top: 0;
            left: 0;
            width: 210mm;
        }

        .item-request-toolbar {
            display: none;
        }

        .item-request-paper {
            margin: 0;
            box-shadow: none;
        }
    }
</style>

<div class="item-request-document item-request-document--{{ $mode }}">
    @if ($mode === 'preview')
        <div class="item-request-toolbar">
            <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
        </div>
    @endif

    <main class="item-request-paper">
        <table class="item-request-header">
            <tbody>
                <tr>
                    <td>
                        <div class="item-request-brand">RSV</div>
                        <div class="item-request-tagline">Helmet Manufacture</div>
                        <div class="item-request-description">PT. RSV MANUFACTURE INDONESIA — Inventory &amp; Procurement System</div>
                    </td>
                    <td class="item-request-title">
                        <strong>Form Permintaan Barang</strong>
                        <span>{{ $itemRequest->code }}</span>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="item-request-red-line"></div>

        <h2 class="item-request-section-title">Document Information</h2>
        <div class="item-request-info-wrap">
            <table class="item-request-info">
                <tbody>
                    <tr>
                        <td>No. Permintaan</td>
                        <td>{{ $itemRequest->code }}</td>
                        <td>Tanggal Form</td>
                        <td>{{ $itemRequest->created_at?->locale('id')->translatedFormat('d F Y') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td>Departemen</td>
                        <td>{{ $itemRequest->division?->name ?? '—' }}</td>
                        <td>Tgl. Dibutuhkan</td>
                        <td class="item-request-blank">................................</td>
                    </tr>
                    <tr>
                        <td>Peminta</td>
                        <td>{{ $itemRequest->requester_name }}</td>
                        <td>Prioritas</td>
                        <td class="item-request-blank">................................</td>
                    </tr>
                    <tr>
                        <td>Kebutuhan Untuk</td>
                        <td colspan="3">{{ $itemRequest->note ?: '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h2 class="item-request-section-title">Item Details</h2>
        <table class="item-request-items">
            <thead>
                <tr>
                    <th class="item-request-col-no">No</th>
                    <th class="item-request-col-name">Nama Barang / Bahan</th>
                    <th class="item-request-col-spec">Spesifikasi</th>
                    <th class="item-request-col-qty">Jml</th>
                    <th class="item-request-col-unit">Satuan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($itemLines as $index => $line)
                    <tr>
                        <td class="item-request-no">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</td>
                        <td>{{ $line->item?->item_name ?? $line->requested_item_name }}</td>
                        <td>{{ $line->note }}</td>
                        <td>{{ $line->formattedQuantity() }}</td>
                        <td>{{ $line->item?->baseUom?->code }}</td>
                    </tr>
                @endforeach

                @for ($index = $itemLines->count(); $index < $rowCount; $index++)
                    <tr>
                        <td class="item-request-no">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <h2 class="item-request-section-title">Request Reason</h2>
        <div class="item-request-reason"></div>

        <table class="item-request-signatures">
            <tbody>
                <tr>
                    <td class="item-request-signature">
                        <div class="item-request-signature-title">Dibuat Oleh
(PIC)</div>
                        <div class="item-request-signature-name">({{ $itemRequest->requester_name }})</div>
                    </td>
                    <td class="item-request-signature">
                        <div class="item-request-signature-title">Diperiksa Oleh
(Kepala Produksi)</div>
                        <div class="item-request-signature-name">(............................)</div>
                    </td>
                    <td class="item-request-signature">
                        <div class="item-request-signature-title">Disetujui Oleh
(Inventori Kontrol)</div>
                        <div class="item-request-signature-name">(............................)</div>
                    </td>
                </tr>
            </tbody>
        </table>
    </main>
</div>
