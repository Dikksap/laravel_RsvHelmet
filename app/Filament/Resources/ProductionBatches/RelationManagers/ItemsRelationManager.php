<?php

namespace App\Filament\Resources\ProductionBatches\RelationManagers;

use App\Models\Item;
use App\Models\ProductionBatchItem;
use App\Services\BatchRequirementService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use MrPunyapal\FilamentInfiniteSelect\InfiniteSelect;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Produk yang dibuat';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                InfiniteSelect::make('item_id')
                    ->label('Barang')
                    ->getPaginatedOptionsUsing(function (int $offset, int $limit, ?string $search): array {
                        $query = Item::query()
                            ->where('is_stockable', true)
                            ->where('has_variants', false)
                            ->when(filled($search), fn (Builder $query): Builder => $query->where(
                                fn (Builder $query): Builder => $query
                                    ->where('item_code', 'like', "%{$search}%")
                                    ->orWhere('item_name', 'like', "%{$search}%"),
                            ));

                        $rows = $query->orderBy('item_name')->skip($offset)->take($limit + 1)->get();

                        return [
                            'options' => $rows->take($limit)
                                ->mapWithKeys(fn (Item $item): array => [
                                    $item->getKey() => "{$item->item_code} — {$item->item_name}",
                                ])
                                ->all(),
                            'hasMore' => $rows->count() > $limit,
                        ];
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        $item = Item::query()->find($value);

                        return $item ? "{$item->item_code} — {$item->item_name}" : null;
                    })
                    ->required()
                    ->rule(fn (?ProductionBatchItem $record) => Rule::unique('production_batch_items', 'item_id')
                        ->where('production_batch_id', $this->getOwnerRecord()->getKey())
                        ->ignore($record?->getKey()))
                    ->validationMessages([
                        'unique' => 'Barang ini sudah ada di batch.',
                    ]),
                TextInput::make('quantity_planned')
                    ->label('Rencana jumlah')
                    ->numeric()
                    ->minValue(0.001)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item.item_code')
                    ->label('Kode')
                    ->searchable(),
                TextColumn::make('item.item_name')
                    ->label('Barang')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('quantity_planned')
                    ->label('Rencana')
                    ->numeric(maxDecimalPlaces: 3),
                TextColumn::make('actual_quantity')
                    ->label('Realisasi')
                    ->numeric(maxDecimalPlaces: 3),
                TextColumn::make('difference')
                    ->label('Selisih')
                    ->state(fn (ProductionBatchItem $record): float => $record->actual_quantity - (float) $record->quantity_planned)
                    ->numeric(maxDecimalPlaces: 3)
                    ->color(fn (float $state): string => $state < 0 ? 'danger' : 'success'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah produk')
                    ->after(fn () => $this->recalculateRequirements()),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(fn () => $this->recalculateRequirements()),
                DeleteAction::make()
                    ->after(fn () => $this->recalculateRequirements()),
            ]);
    }

    private function recalculateRequirements(): void
    {
        app(BatchRequirementService::class)->recalculate($this->getOwnerRecord());
    }
}
