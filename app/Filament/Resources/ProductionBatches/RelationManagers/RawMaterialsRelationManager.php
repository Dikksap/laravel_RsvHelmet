<?php

namespace App\Filament\Resources\ProductionBatches\RelationManagers;

use App\Models\Item;
use App\Models\ProductionBatch;
use App\Services\BomExplosionService;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

class RawMaterialsRelationManager extends RelationManager
{
    protected static string $relationship = 'materials';

    protected static ?string $title = 'Rincian bahan baku';

    protected static string|BackedEnum|null $icon = 'heroicon-o-beaker';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => $this->rawMaterials())
            ->description('Bahan baku mentah hasil urai komposisi bertingkat. Hanya untuk informasi; pemotongan stok tetap memakai material langsung di tab Kebutuhan material.')
            ->columns([
                TextColumn::make('item_code')
                    ->label('Kode')
                    ->wrap(),
                TextColumn::make('item_name')
                    ->label('Bahan baku')
                    ->wrap(),
                TextColumn::make('required_quantity')
                    ->label('Kebutuhan')
                    ->numeric(maxDecimalPlaces: 3),
                TextColumn::make('stock_on_hand')
                    ->label('Stok gudang material')
                    ->numeric(maxDecimalPlaces: 3),
                TextColumn::make('shortage_quantity')
                    ->label('Kekurangan')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => (float) $state > 0
                        ? Number::format((float) $state, maxPrecision: 3, locale: app()->getLocale())
                        : 'Cukup')
                    ->color(fn (mixed $state): string => (float) $state > 0 ? 'danger' : 'success'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Item $record): string => (float) $record->shortage_quantity > 0 ? 'Kurang' : 'Cukup')
                    ->color(fn (string $state): string => $state === 'Kurang' ? 'danger' : 'success'),
            ])
            ->paginated(false)
            ->emptyStateHeading('Belum ada bahan baku mentah')
            ->emptyStateDescription('Pastikan produk pada batch punya komposisi barang sampai ke bahan baku.');
    }

    /**
     * @return Collection<int, Item>
     */
    private function rawMaterials(): Collection
    {
        $batch = $this->getOwnerRecord();

        assert($batch instanceof ProductionBatch);

        return app(BomExplosionService::class)
            ->rawMaterials($batch)
            ->map(function (array $row): Item {
                /** @var Item $item */
                $item = $row['item'];

                $item->setAttribute('required_quantity', $row['quantity_required']);
                $item->setAttribute('stock_on_hand', $row['stock_on_hand']);
                $item->setAttribute('shortage_quantity', $row['shortage_quantity']);

                return $item;
            })
            ->values();
    }
}
