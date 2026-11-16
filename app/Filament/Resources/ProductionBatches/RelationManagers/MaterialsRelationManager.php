<?php

namespace App\Filament\Resources\ProductionBatches\RelationManagers;

use App\Models\ProductionBatchMaterial;
use App\Services\BatchRequirementService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class MaterialsRelationManager extends RelationManager
{
    protected static string $relationship = 'materials';

    protected static ?string $title = 'Kebutuhan material';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('item.components'))
            ->columns([
                TextColumn::make('item.item_code')
                    ->label('Kode')
                    ->searchable(),
                TextColumn::make('item.item_name')
                    ->label('Material')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('component_count')
                    ->label('Komponen')
                    ->badge()
                    ->state(function (ProductionBatchMaterial $record): string {
                        $count = $record->item?->components
                            ->where('is_active', true)
                            ->count() ?? 0;

                        return $count > 0 ? "{$count} komponen" : '—';
                    })
                    ->color(fn (string $state): string => $state === '—' ? 'gray' : 'info')
                    ->tooltip(fn (string $state): ?string => $state === '—'
                        ? null
                        : 'Material ini masih setengah jadi. Bahan baku mentahnya ada di tab Rincian bahan baku.'),
                TextColumn::make('quantity_required')
                    ->label('Kebutuhan')
                    ->numeric(maxDecimalPlaces: 3),
                TextColumn::make('actual_quantity')
                    ->label('Realisasi')
                    ->numeric(maxDecimalPlaces: 3),
                TextColumn::make('stock_on_hand')
                    ->label('Stok gudang material')
                    ->numeric(maxDecimalPlaces: 3),
                TextColumn::make('variance')
                    ->label('Selisih')
                    ->state(fn (ProductionBatchMaterial $record): float => $record->actual_quantity - (float) $record->quantity_required)
                    ->numeric(maxDecimalPlaces: 3)
                    ->color(fn (float $state): string => $state < 0 ? 'warning' : 'success'),
                TextColumn::make('shortage_quantity')
                    ->label('Kekurangan')
                    ->badge()
                    ->state(fn (ProductionBatchMaterial $record): string => Number::format(
                        $record->shortage_quantity,
                        maxPrecision: 3,
                        locale: app()->getLocale(),
                    ))
                    ->color(fn (ProductionBatchMaterial $record): string => $record->shortage_quantity > 0 ? 'danger' : 'success')
                    ->formatStateUsing(fn (string $state): string => $state === '0' ? 'Cukup' : $state),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (ProductionBatchMaterial $record): string => match (true) {
                        $record->shortage_quantity > 0 => 'Kurang',
                        $record->actual_quantity > 0 => 'Sebagian / selesai',
                        default => 'Belum dipakai',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Kurang' => 'danger',
                        'Sebagian / selesai' => 'info',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                Action::make('recalculate')
                    ->label('Hitung ulang')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(function (): void {
                        app(BatchRequirementService::class)->recalculate($this->getOwnerRecord());

                        Notification::make()
                            ->title('Kebutuhan material diperbarui')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Belum ada kebutuhan material')
            ->emptyStateDescription('Tambahkan produk pada tab "Produk yang dibuat", lalu pastikan barang tersebut punya komposisi barang.');
    }
}
