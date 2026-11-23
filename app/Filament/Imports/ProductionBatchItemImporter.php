<?php

namespace App\Filament\Imports;

use App\Models\Item;
use App\Models\ItemAttribute;
use App\Models\ItemAttributeValue;
use App\Models\ProductionBatch;
use App\Models\ProductionBatchItem;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class ProductionBatchItemImporter extends Importer
{
    protected static ?string $model = ProductionBatchItem::class;

    private float $resolvedQuantity = 0;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('item')
                ->label('item')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->fillRecordUsing(fn (): null => null)
                ->example('Windbreaker Motif BOB'),

            ImportColumn::make('size')
                ->label('size')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:50'])
                ->fillRecordUsing(fn (): null => null)
                ->example('LG'),

            ImportColumn::make('stok')
                ->label('stok')
                ->numeric(decimalPlaces: 0)
                ->requiredMapping()
                ->rules(['required', 'numeric', 'min:0'])
                ->fillRecordUsing(fn (): null => null)
                ->example('538'),
        ];
    }

    public function resolveRecord(): ?ProductionBatchItem
    {
        $batchId = $this->options['production_batch_id'] ?? null;

        if (! $batchId) {
            throw new RowImportFailedException('ID batch produksi tidak ditemukan.');
        }

        $batch = ProductionBatch::query()->find($batchId);

        if (! $batch) {
            throw new RowImportFailedException('Batch produksi tidak ditemukan.');
        }

        $parentItemName = trim((string) ($this->data['item'] ?? ''));
        $sizeValue = trim((string) ($this->data['size'] ?? ''));

        if ($parentItemName === '' || $sizeValue === '') {
            throw new RowImportFailedException('Kolom item dan size wajib diisi.');
        }

        // Find the parent item by name.
        $parentItem = Item::query()
            ->where('item_name', $parentItemName)
            ->whereNull('parent_item_id')
            ->first();

        if (! $parentItem) {
            throw new RowImportFailedException("Barang induk [{$parentItemName}] tidak ditemukan.");
        }

        // Find the size attribute.
        $sizeAttribute = ItemAttribute::query()->where('name', 'Ukuran')->first();

        if (! $sizeAttribute) {
            throw new RowImportFailedException('Atribut ukuran (Ukuran) tidak ditemukan di sistem.');
        }

        // Find the size attribute value.
        $sizeAttributeValue = ItemAttributeValue::query()
            ->where('attribute_id', $sizeAttribute->getKey())
            ->where('value', $sizeValue)
            ->first();

        if (! $sizeAttributeValue) {
            throw new RowImportFailedException("Ukuran [{$sizeValue}] tidak ditemukan.");
        }

        // Find the variant item matching parent + size.
        $variantItem = Item::query()
            ->where('parent_item_id', $parentItem->getKey())
            ->whereHas(
                'variantMappings',
                fn ($q) => $q
                    ->where('attribute_id', $sizeAttribute->getKey())
                    ->where('attribute_value_id', $sizeAttributeValue->getKey()),
            )
            ->first();

        if (! $variantItem) {
            throw new RowImportFailedException(
                "Varian [{$parentItemName} {$sizeValue}] tidak ditemukan.",
            );
        }

        $this->resolvedQuantity = (float) ($this->data['stok'] ?? 0);

        // Find existing batch item or create new one.
        return ProductionBatchItem::query()->firstOrNew([
            'production_batch_id' => $batchId,
            'item_id' => $variantItem->getKey(),
        ]);
    }

    public function saveRecord(): void
    {
        $this->record->quantity_planned = $this->resolvedQuantity;
        $this->record->save();
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
        $body = 'Impor rencana produksi selesai: '
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
