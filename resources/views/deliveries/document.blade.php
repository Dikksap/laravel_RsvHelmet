@php
    $deliveryLines = $delivery->lines->sortBy('id')->values();
    $rowCount = max(10, $deliveryLines->count());
@endphp

<style>
    .delivery-note-document {
        color: #111827;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 10pt;
    }

    .delivery-note-document *,
    .delivery-note-document *::before,
    .delivery-note-document *::after {
        box-sizing: border-box;
    }

    .delivery-note-paper {
        box-sizing: content-box;
        width: 178mm;
        margin: 0 auto;
        padding: 14mm 16mm;
        background: #fff;
    }

    .delivery-note-letterhead,
    .delivery-note-info,
    .delivery-note-items,
    .delivery-note-signatures {
        width: 100%;
        border-collapse: collapse;
    }

    .delivery-note-company {
        padding-bottom: 2.5mm;
    }

    .delivery-note-company-name {
        color: #111827;
        font-size: 14pt;
        font-weight: 700;
        letter-spacing: 0.04em;
    }

    .delivery-note-company-line {
        margin-top: 1mm;
        color: #4b5563;
        font-size: 8pt;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .delivery-note-header-rule {
        margin-bottom: 6mm;
        border-top: 1.2pt solid #111827;
        border-bottom: 0.4pt solid #111827;
        height: 0.8mm;
    }

    .delivery-note-heading {
        text-align: center;
    }

    .delivery-note-title {
        margin: 0;
        color: #111827;
        font-size: 15pt;
        font-weight: 700;
        letter-spacing: 0.35em;
        text-indent: 0.35em;
        text-transform: uppercase;
    }

    .delivery-note-number {
        margin-top: 1.5mm;
        color: #374151;
        font-size: 9.5pt;
    }

    .delivery-note-divider {
        margin: 4mm 0 6mm;
        border-top: 0.4pt solid #9ca3af;
    }

    .delivery-note-section-title {
        margin: 0 0 2mm;
        color: #111827;
        font-size: 9pt;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .delivery-note-info-wrap {
        margin-bottom: 6mm;
        padding: 3.5mm 4mm;
        border: 0.6pt solid #d1d5db;
        background: #f9fafb;
    }

    .delivery-note-info td {
        height: 6.5mm;
        vertical-align: middle;
        font-size: 9.5pt;
    }

    .delivery-note-info td:nth-child(odd) {
        width: 22%;
        color: #6b7280;
        font-size: 8.5pt;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .delivery-note-info td:nth-child(even) {
        width: 28%;
        color: #111827;
        font-weight: 700;
    }

    .delivery-note-items {
        margin-bottom: 6mm;
        table-layout: fixed;
        font-size: 9pt;
    }

    .delivery-note-items tr,
    .delivery-note-note,
    .delivery-note-signatures {
        page-break-inside: avoid;
    }

    .delivery-note-items thead {
        display: table-header-group;
    }

    .delivery-note-items th {
        padding: 2mm 1.5mm;
        border: 0.6pt solid #6b7280;
        border-bottom: 1pt solid #111827;
        background: #e5e7eb;
        color: #111827;
        font-size: 8.5pt;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-align: center;
        text-transform: uppercase;
    }

    .delivery-note-items td {
        height: 6mm;
        padding: 1.5mm 2mm;
        border: 0.6pt solid #9ca3af;
        overflow-wrap: anywhere;
    }

    .delivery-note-items tbody tr:nth-child(even) {
        background: #f9fafb;
    }

    .delivery-note-col-no { width: 7%; }
    .delivery-note-col-code { width: 17%; }
    .delivery-note-col-name { width: 31%; }
    .delivery-note-col-spec { width: 23%; }
    .delivery-note-col-qty { width: 11%; }
    .delivery-note-col-unit { width: 11%; }

    .delivery-note-items td.delivery-note-no {
        color: #374151;
        text-align: center;
    }

    .delivery-note-items td.delivery-note-qty {
        text-align: right;
    }

    .delivery-note-items td.delivery-note-unit {
        text-align: center;
    }

    .delivery-note-note {
        min-height: 18mm;
        margin-bottom: 10mm;
        padding: 3mm 4mm;
        border: 0.6pt solid #9ca3af;
        color: #111827;
        font-size: 9.5pt;
        white-space: pre-line;
    }

    .delivery-note-signatures {
        table-layout: fixed;
        text-align: center;
        font-size: 9.5pt;
    }

    .delivery-note-signature {
        width: 50%;
        padding: 0 8mm;
        vertical-align: top;
    }

    .delivery-note-signature-title {
        color: #111827;
        font-weight: 700;
    }

    .delivery-note-signature-space {
        height: 22mm;
    }

    .delivery-note-signature-name {
        color: #111827;
        letter-spacing: 0.02em;
    }

    .delivery-note-blank {
        color: #6b7280;
    }
</style>

<div class="delivery-note-document">
    <main class="delivery-note-paper">
        <table class="delivery-note-letterhead">
            <tbody>
                <tr>
                    <td class="delivery-note-company">
                        <div class="delivery-note-company-name">PT. RSV MANUFACTURE INDONESIA</div>
                        <div class="delivery-note-company-line">Helmet Manufacture</div>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="delivery-note-header-rule"></div>

        <div class="delivery-note-heading">
            <h1 class="delivery-note-title">Surat Jalan</h1>
            <div class="delivery-note-number">Nomor: {{ $delivery->code }}</div>
        </div>

        <div class="delivery-note-divider"></div>

        <h2 class="delivery-note-section-title">Informasi Pengiriman</h2>
        <div class="delivery-note-info-wrap">
            <table class="delivery-note-info">
                <tbody>
                    <tr>
                        <td>Tanggal Kirim</td>
                        <td>{{ $delivery->delivery_date?->locale('id')->translatedFormat('d F Y') ?? '—' }}</td>
                        <td>Gudang Asal</td>
                        <td>{{ $delivery->warehouse?->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td>Penerima</td>
                        <td>{{ $delivery->recipient_name }}</td>
                        <td>Jumlah Jenis Barang</td>
                        <td>{{ $deliveryLines->count() }} jenis</td>
                    </tr>
                    <tr>
                        <td>No. Pengiriman</td>
                        <td colspan="3">{{ $delivery->code }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h2 class="delivery-note-section-title">Rincian Barang</h2>
        <table class="delivery-note-items">
            <thead>
                <tr>
                    <th class="delivery-note-col-no">No</th>
                    <th class="delivery-note-col-code">Kode</th>
                    <th class="delivery-note-col-name">Nama Barang</th>
                    <th class="delivery-note-col-spec">Spesifikasi</th>
                    <th class="delivery-note-col-qty">Jml</th>
                    <th class="delivery-note-col-unit">Satuan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($deliveryLines as $index => $line)
                    <tr>
                        <td class="delivery-note-no">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</td>
                        <td>{{ $line->item?->item_code }}</td>
                        <td>{{ $line->item?->item_name }}</td>
                        <td>{{ $line->note ?: $line->item?->description }}</td>
                        <td class="delivery-note-qty">{{ $line->formattedQuantity() }}</td>
                        <td class="delivery-note-unit">{{ $line->item?->baseUom?->code ?? '—' }}</td>
                    </tr>
                @endforeach

                @for ($index = $deliveryLines->count(); $index < $rowCount; $index++)
                    <tr>
                        <td class="delivery-note-no">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <h2 class="delivery-note-section-title">Catatan</h2>
        <div class="delivery-note-note">{{ filled($delivery->note) ? $delivery->note : '—' }}</div>

        <table class="delivery-note-signatures">
            <tbody>
                <tr>
                    <td class="delivery-note-signature">
                        <div class="delivery-note-signature-title">Hormat Kami,</div>
                        <div class="delivery-note-signature-space"></div>
                        <div class="delivery-note-signature-name delivery-note-blank">(.......................................)</div>
                    </td>
                    <td class="delivery-note-signature">
                        <div class="delivery-note-signature-title">Penerima,</div>
                        <div class="delivery-note-signature-space"></div>
                        <div class="delivery-note-signature-name delivery-note-blank">(.......................................)</div>
                    </td>
                </tr>
            </tbody>
        </table>
    </main>
</div>
