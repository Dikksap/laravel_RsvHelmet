<?php

namespace App\Filament\Exports;

use App\Models\InventoryBalance;
use App\Models\ItemAttribute;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductStockExporter extends Exporter
{
    protected static ?string $model = InventoryBalance::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('item')
                ->label('item')
                ->state(fn (InventoryBalance $record): string => $record->parent_item_name ?? ''),

            ExportColumn::make('size')
                ->label('size')
                ->state(fn (InventoryBalance $record): string => $record->size_value ?? ''),

            ExportColumn::make('stok')
                ->label('stok')
                ->state(fn (InventoryBalance $record): string => (string) (int) (float) ($record->total_stock ?? 0)),
        ];
    }

    /**
     * Returns one row per (parent item, size) combination with the total stock
     * summed across all warehouses.
     */
    public static function getEloquentQuery(): Builder
    {
        $sizeAttributeId = ItemAttribute::query()
            ->where('name', 'Ukuran')
            ->value('id');

        return InventoryBalance::query()
            ->select([
                // We need a unique primary key for Filament's exporter — use the
                // minimum balance ID per group as a stable representative ID.
                DB::raw('MIN(inventory_balances.id) as id'),
                DB::raw('parent_items.item_name as parent_item_name'),
                DB::raw('iav.value as size_value'),
                DB::raw('SUM(inventory_balances.quantity_on_hand) as total_stock'),
            ])
            ->join('items as variant_items', 'variant_items.id', '=', 'inventory_balances.item_id')
            ->join('items as parent_items', 'parent_items.id', '=', 'variant_items.parent_item_id')
            ->join('item_variant_mappings as ivm', function ($join) use ($sizeAttributeId): void {
                $join->on('ivm.item_id', '=', 'variant_items.id')
                    ->where('ivm.attribute_id', '=', $sizeAttributeId);
            })
            ->join('item_attribute_values as iav', 'iav.id', '=', 'ivm.attribute_value_id')
            ->whereNotNull('variant_items.parent_item_id')
            ->groupBy('parent_items.id', 'parent_items.item_name', 'iav.id', 'iav.value')
            ->orderBy('parent_items.item_name')
            ->orderBy('iav.value');
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
        $body = 'Ekspor stok produk selesai: '
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
