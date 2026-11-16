<?php

namespace App\Filament\Resources\Warehouses\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BalancesRelationManager extends RelationManager
{
    protected static string $relationship = 'balances';

    protected static ?string $title = 'Stok di gudang ini';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item.item_code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('item.item_name')
                    ->label('Barang')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('quantity_on_hand')
                    ->label('Stok')
                    ->numeric(maxDecimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('minimum_quantity')
                    ->label('Stok minimum')
                    ->numeric(maxDecimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('stock_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'out' => 'Habis',
                        'low' => 'Menipis',
                        default => 'Aman',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'out' => 'danger',
                        'low' => 'warning',
                        default => 'success',
                    }),
            ]);
    }
}
