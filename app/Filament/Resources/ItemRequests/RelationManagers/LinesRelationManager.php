<?php

namespace App\Filament\Resources\ItemRequests\RelationManagers;

use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Barang yang diminta';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('item.baseUom'))
            ->columns([
                TextColumn::make('item.item_name')
                    ->label('Barang terpetakan')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('requested_item_name')
                    ->label('Nama manual')
                    ->placeholder('—')
                    ->searchable()
                    ->limit(40),
                IconColumn::make('is_mapped')
                    ->label('Terpetakan')
                    ->boolean(),
                TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->numeric(maxDecimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('item.baseUom.code')
                    ->label('Satuan')
                    ->placeholder('—'),
                TextColumn::make('note')
                    ->label('Catatan')
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->filters([
                TernaryFilter::make('item_id')
                    ->label('Status pemetaan')
                    ->nullable()
                    ->placeholder('Semua')
                    ->trueLabel('Sudah dipetakan')
                    ->falseLabel('Belum dipetakan'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Petakan barang')
                    ->modalHeading('Petakan barang permintaan')
                    ->modalSubmitActionLabel('Simpan pemetaan')
                    ->schema([
                        TextInput::make('requested_item_name')
                            ->label('Nama permintaan manual')
                            ->disabled(),
                        Select::make('item_id')
                            ->label('Barang')
                            ->relationship('item', 'item_name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),
            ])
            ->defaultSort('id');
    }
}
