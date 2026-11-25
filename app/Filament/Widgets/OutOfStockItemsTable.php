<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\InventoryBalances\InventoryBalanceResource;
use App\Models\InventoryBalance;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OutOfStockItemsTable extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Item habis')
            ->description('Barang dengan stok habis atau sudah mencapai stok minimum.')
            ->query(
                InventoryBalance::query()
                    ->with(['item', 'warehouse'])
                    ->where(fn (Builder $query): Builder => $query
                        ->where('quantity_on_hand', '<=', 0)
                        ->orWhere(fn (Builder $query): Builder => $query
                            ->where('quantity_on_hand', '>', 0)
                            ->whereColumn('quantity_on_hand', '<=', 'minimum_quantity')))
                    ->orderBy('quantity_on_hand')
                    ->orderByDesc('minimum_quantity')
                    ->limit(8)
            )
            ->columns([
                TextColumn::make('item.item_code')
                    ->label('Kode'),
                TextColumn::make('item.item_name')
                    ->label('Barang')
                    ->wrap(),
                TextColumn::make('warehouse.name')
                    ->label('Gudang'),
                TextColumn::make('quantity_on_hand')
                    ->label('Stok')
                    ->numeric(maxDecimalPlaces: 3),
                TextColumn::make('minimum_quantity')
                    ->label('Stok minimum')
                    ->numeric(maxDecimalPlaces: 3),
                InventoryBalanceResource::stockStatusColumn(),
            ])
            ->headerActions([
                Action::make('viewAll')
                    ->label('Lihat semua')
                    ->icon('heroicon-o-arrow-right')
                    ->color('gray')
                    ->url(InventoryBalanceResource::getUrl()),
            ])
            ->paginated(false)
            ->emptyStateHeading('Semua stok aman')
            ->emptyStateDescription('Barang habis atau menipis akan muncul di sini.');
    }
}
