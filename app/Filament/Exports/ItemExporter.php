<?php

namespace App\Filament\Exports;

use App\Models\Item;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ItemExporter extends Exporter
{
    protected static ?string $model = Item::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('item_code')
                ->label('Kode barang'),
            ExportColumn::make('barcode')
                ->label('Barcode'),
            ExportColumn::make('item_name')
                ->label('Nama barang'),
            ExportColumn::make('description')
                ->label('Deskripsi'),
            ExportColumn::make('type.name')
                ->label('Jenis barang'),
            ExportColumn::make('category.name')
                ->label('Kategori'),
            ExportColumn::make('brand.name')
                ->label('Merek'),
            ExportColumn::make('baseUom.code')
                ->label('Satuan dasar'),
            ExportColumn::make('parent.item_code')
                ->label('Kode barang induk'),
            ExportColumn::make('has_variants')
                ->label('Punya varian'),
            ExportColumn::make('weight')
                ->label('Berat'),
            ExportColumn::make('weightUom.code')
                ->label('Satuan berat'),
            ExportColumn::make('length')
                ->label('Panjang'),
            ExportColumn::make('width')
                ->label('Lebar'),
            ExportColumn::make('height')
                ->label('Tinggi'),
            ExportColumn::make('dimensionUom.code')
                ->label('Satuan dimensi'),
            ExportColumn::make('base_price')
                ->label('Harga dasar'),
            ExportColumn::make('standard_cost')
                ->label('Harga pokok standar'),
            ExportColumn::make('is_stockable')
                ->label('Bisa distok'),
            ExportColumn::make('is_sellable')
                ->label('Bisa dijual'),
            ExportColumn::make('is_purchasable')
                ->label('Bisa dibeli'),
            ExportColumn::make('is_active')
                ->label('Aktif'),
            ExportColumn::make('created_at')
                ->label('Dibuat'),
            ExportColumn::make('updated_at')
                ->label('Diperbarui'),
        ];
    }

    /**
     * Run exports synchronously so they work without a queue worker.
     * Remove this override and run `php artisan queue:work` for large exports.
     */
    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Ekspor barang selesai: '
            .number_format($export->successful_rows)
            .' baris berhasil diekspor.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '
                .number_format($failedRowsCount)
                .' baris gagal diekspor.';
        }

        return $body;
    }
}
