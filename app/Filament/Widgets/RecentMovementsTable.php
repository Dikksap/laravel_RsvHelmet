<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Models\InventoryMovement;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Number;

class RecentMovementsTable extends TableWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Transaksi terbaru')
            ->description('Transaksi stok terakhir yang tercatat.')
            ->query(
                InventoryMovement::query()
                    ->with(['item', 'warehouse', 'productionBatch'])
                    ->orderByDesc('occurred_at')
                    ->orderByDesc('created_at')
                    ->limit(8)
            )
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Waktu')
                    ->dateTime(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => InventoryMovement::typeOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match (true) {
                        in_array($state, InventoryMovement::incomingTypes(), true) => 'success',
                        default => 'danger',
                    }),
                TextColumn::make('item.item_code')
                    ->label('Kode'),
                TextColumn::make('item.item_name')
                    ->label('Barang')
                    ->wrap(),
                TextColumn::make('warehouse.name')
                    ->label('Gudang'),
                TextColumn::make('quantity')
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
                TextColumn::make('productionBatch.batch_code')
                    ->label('Batch')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->headerActions([
                Action::make('viewAll')
                    ->label('Lihat semua')
                    ->icon('heroicon-o-arrow-right')
                    ->color('gray')
                    ->url(InventoryMovementResource::getUrl('index')),
            ])
            ->paginated(false)
            ->emptyStateHeading('Belum ada transaksi stok')
            ->emptyStateDescription('Transaksi stok baru akan muncul di sini setelah dicatat.');
    }
}
