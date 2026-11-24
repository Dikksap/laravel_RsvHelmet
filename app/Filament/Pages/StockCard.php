<?php

namespace App\Filament\Pages;

use App\Models\InventoryMovement;
use App\Models\Item;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;
use UnitEnum;

class StockCard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = 'Gudang';

    protected static ?string $navigationLabel = 'Kartu Stok';

    protected static ?string $title = 'Kartu Stok';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament.pages.stock-card';

    public function getSubheading(): ?string
    {
        return 'Riwayat masuk dan keluar barang beserta saldo berjalan. Gunakan filter untuk melihat satu barang di satu gudang.';
    }

    public function table(Table $table): Table
    {
        $incomingTypes = implode(', ', array_map(
            fn (string $type): string => "'{$type}'",
            InventoryMovement::incomingTypes(),
        ));

        return $table
            ->query(
                InventoryMovement::query()
                    ->select('inventory_movements.*')
                    ->selectRaw(
                        "SUM(CASE WHEN inventory_movements.type IN ({$incomingTypes}) THEN inventory_movements.quantity ELSE -inventory_movements.quantity END) OVER (PARTITION BY inventory_movements.item_id, inventory_movements.warehouse_id ORDER BY inventory_movements.occurred_at, inventory_movements.id) AS running_balance"
                    )
            )
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Waktu')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('item.item_code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('item.item_name')
                    ->label('Barang')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => InventoryMovement::typeOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => in_array($state, InventoryMovement::incomingTypes(), true) ? 'success' : 'danger'),
                TextColumn::make('quantity_in')
                    ->label('Masuk')
                    ->state(fn (InventoryMovement $record): ?string => $record->isIncoming()
                        ? Number::format((float) $record->quantity, maxPrecision: 3, locale: app()->getLocale())
                        : null)
                    ->color('success'),
                TextColumn::make('quantity_out')
                    ->label('Keluar')
                    ->state(fn (InventoryMovement $record): ?string => $record->isIncoming()
                        ? null
                        : Number::format((float) $record->quantity, maxPrecision: 3, locale: app()->getLocale()))
                    ->color('danger'),
                TextColumn::make('running_balance')
                    ->label('Saldo')
                    ->numeric(maxDecimalPlaces: 3)
                    ->weight('bold'),
                TextColumn::make('productionBatch.batch_code')
                    ->label('Batch')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('reference')
                    ->label('Referensi')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('note')
                    ->label('Catatan')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('item_id')
                    ->label('Barang')
                    ->options(fn (): array => Item::query()
                        ->where('is_stockable', true)
                        ->orderBy('item_name')
                        ->get()
                        ->mapWithKeys(fn (Item $item): array => [$item->id => "{$item->item_code} — {$item->item_name}"])
                        ->all())
                    ->searchable(),
                SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->relationship('warehouse', 'name'),
                SelectFilter::make('production_batch_id')
                    ->label('Batch produksi')
                    ->relationship('productionBatch', 'batch_code')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Belum ada transaksi stok')
            ->emptyStateDescription('Transaksi yang dicatat lewat menu Transaksi Stok akan muncul di sini.');
    }
}
