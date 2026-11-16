<?php

namespace App\Filament\Resources\Items\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InventoryBalancesRelationManager extends RelationManager
{
    protected static string $relationship = 'inventoryBalances';

    protected static ?string $title = 'Stok di gudang';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('warehouse.code')
                    ->label('Kode gudang')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->searchable()
                    ->sortable(),
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
