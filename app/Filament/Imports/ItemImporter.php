<?php

namespace App\Filament\Imports;

use App\Models\Item;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class ItemImporter extends Importer
{
    protected static ?string $model = Item::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('item_code')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:50'])
                ->example('HELM-001'),
            ImportColumn::make('barcode')
                ->ignoreBlankState()
                ->rules(['nullable', 'string', 'max:100'])
                ->example('8991234567890'),
            ImportColumn::make('item_name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->example('Helmet RSV'),
            ImportColumn::make('description')
                ->ignoreBlankState(),
            ImportColumn::make('item_type_id')
                ->label('Jenis barang')
                ->relationship('type', 'name')
                ->requiredMapping()
                ->example('Product'),
            ImportColumn::make('category_id')
                ->label('Kategori')
                ->relationship('category', 'name')
                ->ignoreBlankState()
                ->example('Helm'),
            ImportColumn::make('brand_id')
                ->label('Merek')
                ->relationship('brand', 'name')
                ->ignoreBlankState()
                ->example('RSV'),
            ImportColumn::make('division_id')
                ->label('Divisi')
                ->relationship('division', 'name')
                ->ignoreBlankState()
                ->example('Produksi'),
            ImportColumn::make('base_uom_id')
                ->label('Satuan dasar')
                ->relationship('baseUom', 'code')
                ->requiredMapping()
                ->example('PCS'),
            ImportColumn::make('parent_item_id')
                ->label('Kode barang induk')
                ->relationship('parent', 'item_code')
                ->ignoreBlankState()
                ->example('HELM-000'),
            ImportColumn::make('has_variants')
                ->label('Punya varian')
                ->boolean()
                ->ignoreBlankState()
                ->example('0'),
            ImportColumn::make('weight')
                ->numeric(decimalPlaces: 3)
                ->ignoreBlankState()
                ->example('0.500'),
            ImportColumn::make('weight_uom_id')
                ->label('Satuan berat')
                ->relationship('weightUom', 'code')
                ->ignoreBlankState()
                ->example('KG'),
            ImportColumn::make('length')
                ->numeric(decimalPlaces: 3)
                ->ignoreBlankState()
                ->example('10.000'),
            ImportColumn::make('width')
                ->numeric(decimalPlaces: 3)
                ->ignoreBlankState()
                ->example('10.000'),
            ImportColumn::make('height')
                ->numeric(decimalPlaces: 3)
                ->ignoreBlankState()
                ->example('10.000'),
            ImportColumn::make('dimension_uom_id')
                ->label('Satuan dimensi')
                ->relationship('dimensionUom', 'code')
                ->ignoreBlankState()
                ->example('CM'),
            ImportColumn::make('base_price')
                ->label('Harga dasar')
                ->numeric(decimalPlaces: 2)
                ->ignoreBlankState()
                ->example('150000.00'),
            ImportColumn::make('standard_cost')
                ->label('Harga pokok standar')
                ->numeric(decimalPlaces: 2)
                ->ignoreBlankState()
                ->example('100000.00'),
            ImportColumn::make('is_stockable')
                ->label('Bisa distok')
                ->boolean()
                ->ignoreBlankState()
                ->example('1'),
            ImportColumn::make('is_sellable')
                ->label('Bisa dijual')
                ->boolean()
                ->ignoreBlankState()
                ->example('1'),
            ImportColumn::make('is_purchasable')
                ->label('Bisa dibeli')
                ->boolean()
                ->ignoreBlankState()
                ->example('1'),
            ImportColumn::make('is_active')
                ->label('Aktif')
                ->boolean()
                ->ignoreBlankState()
                ->example('1'),
        ];
    }

    public function resolveRecord(): ?Item
    {
        $itemCode = $this->data['item_code'] ?? null;

        if (blank($itemCode)) {
            return new Item;
        }

        return Item::query()->firstOrNew(['item_code' => $itemCode]);
    }

    /**
     * Run imports synchronously so they work without a queue worker.
     * Remove this override and run `php artisan queue:work` for large imports.
     */
    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Impor barang selesai: '
            .number_format($import->successful_rows)
            .' baris berhasil diimpor.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '
                .number_format($failedRowsCount)
                .' baris gagal diimpor.';
        }

        return $body;
    }
}
