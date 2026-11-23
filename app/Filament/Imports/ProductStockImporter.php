<?php

namespace App\Filament\Imports;

use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\ItemAttribute;
use App\Models\ItemAttributeValue;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class ProductStockImporter extends Importer
{
    protected static ?string $model = InventoryBalance::class;

    /** @var list<string> */
    private array $resolvedBalanceIds = [];

    private float $resolvedNewStock = 0;

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

    public function resolveRecord(): ?InventoryBalance
    {
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

        // Load all balance records for this variant.
        $balances = InventoryBalance::query()
            ->where('item_id', $variantItem->getKey())
            ->get();

        if ($balances->isEmpty()) {
            throw new RowImportFailedException(
                "Stok gudang untuk [{$parentItemName} {$sizeValue}] belum ada. Tambahkan entri stok gudang terlebih dahulu.",
            );
        }

        // Cache what we need for saveRecord().
        $this->resolvedBalanceIds = $balances->pluck('id')->all();
        $this->resolvedNewStock = (float) ($this->data['stok'] ?? 0);

        // Return the first record so the pipeline does not bail out.
        return $balances->first();
    }

    public function saveRecord(): void
    {
        $newTotal = $this->resolvedNewStock;
        $ids = $this->resolvedBalanceIds;

        if (count($ids) === 1) {
            // Single warehouse: set directly.
            InventoryBalance::query()
                ->whereIn('id', $ids)
                ->update(['quantity_on_hand' => $newTotal]);

            return;
        }

        // Multiple warehouses: put full total on the first, zero the rest.
        // This avoids double-counting while still recording the new total.
        $first = true;

        foreach (InventoryBalance::query()->whereIn('id', $ids)->get() as $balance) {
            $balance->quantity_on_hand = $first ? $newTotal : 0;
            $balance->save();
            $first = false;
        }
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
        $body = 'Impor stok produk selesai: '
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
