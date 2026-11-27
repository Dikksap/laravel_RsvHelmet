<?php

namespace App\Filament\Resources\Deliveries\RelationManagers;

use App\Models\InventoryMovement;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Transaksi stok keluar';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Waktu')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => InventoryMovement::typeOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => in_array($state, InventoryMovement::incomingTypes(), true)
                        ? 'success'
                        : 'danger'),
                TextColumn::make('item.item_code')
                    ->label('Kode')
                    ->searchable(),
                TextColumn::make('item.item_name')
                    ->label('Barang')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->numeric(maxDecimalPlaces: 3),
                TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->toggleable(),
                TextColumn::make('reference')
                    ->label('Referensi')
                    ->placeholder('—'),
                TextColumn::make('creator.name')
                    ->label('Dicatat oleh')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->emptyStateHeading('Belum ada transaksi stok')
            ->emptyStateDescription('Transaksi keluar dibuat otomatis saat pengiriman dikirim.');
    }
}
