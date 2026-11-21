<?php

namespace App\Filament\Exports;

use App\Models\StockOpnameLine;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class StockOpnameLineExporter extends Exporter
{
    protected static ?string $model = StockOpnameLine::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('item.item_code')
                ->label('Kode barang'),
            ExportColumn::make('item.item_name')
                ->label('Nama barang'),
            ExportColumn::make('system_quantity')
                ->label('Stok sistem')
                ->formatStateUsing(fn (?string $state): ?string => StockOpnameLine::formatQuantity($state)),
            ExportColumn::make('counted_quantity')
                ->label('Stok fisik')
                ->formatStateUsing(fn (?string $state): ?string => StockOpnameLine::formatQuantity($state)),
            ExportColumn::make('difference_quantity')
                ->label('Selisih')
                ->state(fn (StockOpnameLine $record): ?string => $record->counted_quantity === null
                    ? null
                    : StockOpnameLine::formatQuantity($record->difference_quantity)),
            ExportColumn::make('adjustment_quantity')
                ->label('Penyesuaian')
                ->formatStateUsing(fn (?string $state): ?string => StockOpnameLine::formatQuantity($state)),
            ExportColumn::make('note')
                ->label('Catatan'),
            ExportColumn::make('counter.name')
                ->label('Dihitung oleh'),
            ExportColumn::make('counted_at')
                ->label('Waktu hitung'),
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
        $body = 'Ekspor hitung fisik selesai: '
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
