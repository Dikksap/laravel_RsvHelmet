<?php

namespace App\Filament\Resources\ExternalSyncedBarangs;

use App\Filament\Resources\ExternalSyncedBarangs\Pages\ListExternalSyncedBarangs;
use App\Models\ExternalSyncedBarang;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ExternalSyncedBarangResource extends Resource
{
    protected static ?string $model = ExternalSyncedBarang::class;

    protected static string|UnitEnum|null $navigationGroup = 'Gudang';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Riwayat Unit Masuk';

    protected static ?string $modelLabel = 'Unit Masuk';

    protected static ?string $pluralModelLabel = 'Riwayat Unit Masuk';

    protected static ?int $navigationSort = 36;

    protected static ?string $recordTitleAttribute = 'kode_barang';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_barang')
                    ->label('Kode Seri Unit')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('item.item_name')
                    ->label('Barang RSV')
                    ->searchable()
                    ->description(fn (ExternalSyncedBarang $record): ?string => $record->item?->item_code)
                    ->wrap(),
                TextColumn::make('mapping.external_variant_code')
                    ->label('Kode Varian')
                    ->placeholder('-'),
                TextColumn::make('warehouse.name')
                    ->label('Gudang Penerima')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status API')
                    ->badge()
                    ->color(fn (string $state): string => match (strtoupper($state)) {
                        'FINISHGOOD' => 'success',
                        'OUT' => 'info',
                        'RETUR' => 'warning',
                        'BAD' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('external_occurred_at')
                    ->label('Tgl Produksi')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('synced_at')
                    ->label('Tgl Masuk Stok')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('movement.id')
                    ->label('ID Mutasi')
                    ->formatStateUsing(fn ($state): string => filled($state) ? "#{$state}" : '-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('synced_at', 'desc')
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->options(fn (): array => Warehouse::query()->pluck('name', 'id')->all()),
                SelectFilter::make('status')
                    ->label('Status Eksternal')
                    ->options(fn (): array => ExternalSyncedBarang::query()
                        ->distinct()
                        ->pluck('status', 'status')
                        ->all()),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExternalSyncedBarangs::route('/'),
        ];
    }
}
