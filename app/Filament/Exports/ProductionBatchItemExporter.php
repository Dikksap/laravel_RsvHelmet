<?php

namespace App\Filament\Exports;

use App\Models\ItemAttribute;
use App\Models\ProductionBatchItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductionBatchItemExporter extends Exporter
{
    protected static ?string $model = ProductionBatchItem::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('item')
                ->label('item')
                ->state(fn (ProductionBatchItem $record): string => $record->parent_item_name ?? ''),

            ExportColumn::make('size')
                ->label('size')
                ->state(fn (ProductionBatchItem $record): string => $record->size_value ?? ''),

            ExportColumn::make('stok')
                ->label('stok')
                ->state(fn (ProductionBatchItem $record): string => (string) (int) (float) ($record->quantity_planned ?? 0)),
        ];
    }

    /**
     * Returns one row per batch item, with parent item name and size resolved
     * via joins. The production_batch_id filter is applied externally by the
     * ExportAction (through the table query).
     */
    public static function getEloquentQuery(): Builder
    {
        $sizeAttributeId = ItemAttribute::query()
            ->where('name', 'Ukuran')
            ->value('id');

        return ProductionBatchItem::query()
            ->select([
                'production_batch_items.id',
                'production_batch_items.production_batch_id',
                'production_batch_items.quantity_planned',
                DB::raw('parent_items.item_name as parent_item_name'),
                DB::raw('iav.value as size_value'),
            ])
            ->join('items as variant_items', 'variant_items.id', '=', 'production_batch_items.item_id')
            ->join('items as parent_items', 'parent_items.id', '=', 'variant_items.parent_item_id')
            ->join('item_variant_mappings as ivm', function ($join) use ($sizeAttributeId): void {
                $join->on('ivm.item_id', '=', 'variant_items.id')
                    ->where('ivm.attribute_id', '=', $sizeAttributeId);
            })
            ->join('item_attribute_values as iav', 'iav.id', '=', 'ivm.attribute_value_id')
            ->whereNotNull('variant_items.parent_item_id')
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
        $body = 'Ekspor rencana produksi selesai: '
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
