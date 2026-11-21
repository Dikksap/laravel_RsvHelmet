<?php

namespace App\Filament\Imports;

use App\Models\Item;
use App\Models\StockOpname;
use App\Models\StockOpnameLine;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class StockOpnameLineImporter extends Importer
{
    protected static ?string $model = StockOpnameLine::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('item_code')
                ->label('Kode barang')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:50'])
                ->fillRecordUsing(fn (): null => null)
                ->example('HELM-001'),
            ImportColumn::make('counted_quantity')
                ->label('Stok fisik')
                ->numeric(decimalPlaces: 3)
                ->requiredMapping()
                ->ignoreBlankState()
                ->rules(['nullable', 'numeric', 'min:0'])
                ->example('12'),
            ImportColumn::make('note')
                ->label('Catatan')
                ->ignoreBlankState()
                ->rules(['nullable', 'string', 'max:1000'])
                ->example('Hitung ulang rak A'),
        ];
    }

    public function resolveRecord(): ?StockOpnameLine
    {
        $itemCode = trim((string) ($this->data['item_code'] ?? ''));

        if ($itemCode === '') {
            if ($this->rowIsBlank()) {
                return null;
            }

            throw new RowImportFailedException('Kode barang wajib diisi.');
        }

        $opname = $this->resolveOpname();

        $item = Item::query()->where('item_code', $itemCode)->first();

        if (! $item) {
            throw new RowImportFailedException("Kode barang [{$itemCode}] tidak ditemukan.");
        }

        $line = $opname->lines()->where('item_id', $item->getKey())->first();

        if (! $line) {
            throw new RowImportFailedException("Barang [{$itemCode}] tidak ada pada daftar hitung fisik opname ini.");
        }

        return $line;
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
        $body = 'Impor hitung fisik selesai: '
            .number_format($import->successful_rows)
            .' baris berhasil diimpor.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '
                .number_format($failedRowsCount)
                .' baris gagal diimpor.';
        }

        return $body;
    }

    private function resolveOpname(): StockOpname
    {
        $opname = StockOpname::query()->find($this->options['stock_opname_id'] ?? null);

        if (! $opname) {
            throw new RowImportFailedException('Dokumen stok opname tidak ditemukan.');
        }

        if (! $opname->isDraft()) {
            throw new RowImportFailedException('Dokumen stok opname sudah tidak berstatus draft.');
        }

        return $opname;
    }

    private function rowIsBlank(): bool
    {
        foreach (['item_code', 'counted_quantity', 'note'] as $column) {
            if (filled($this->data[$column] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
