<?php

namespace App\Filament\Resources\Deliveries\RelationManagers;

use App\Filament\Forms\Components\InfiniteSelect;
use App\Models\Delivery;
use App\Models\DeliveryLine;
use App\Models\InventoryBalance;
use App\Models\Item;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Barang dikirim';

    /**
     * @var Collection<string, mixed>|null
     */
    private ?Collection $balances = null;

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('item.baseUom'))
            ->columns([
                TextColumn::make('item.item_code')
                    ->label('Kode')
                    ->searchable()
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('item.item_name')
                    ->label('Barang')
                    ->searchable()
                    ->wrap(),
                TextInputColumn::make('quantity')
                    ->label('Jumlah')
                    ->state(fn (DeliveryLine $record): string => (string) (float) $record->quantity)
                    ->type('number')
                    ->inputMode('decimal')
                    ->step('0.001')
                    ->rules(['numeric', 'min:0.001'])
                    ->disabled(fn (DeliveryLine $record): bool => ! $record->delivery->isDraft()),
                TextColumn::make('available_quantity')
                    ->label('Stok tersedia')
                    ->state(fn (DeliveryLine $record): float => $this->balanceFor($record->item_id))
                    ->numeric(maxDecimalPlaces: 3),
                TextColumn::make('item.baseUom.code')
                    ->label('Satuan')
                    ->placeholder('—'),
                TextColumn::make('note')
                    ->label('Catatan')
                    ->placeholder('—')
                    ->limit(40)
                    ->wrap(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah barang')
                    ->visible(fn (): bool => $this->ownerIsDraft())
                    ->schema([
                        InfiniteSelect::make('item_id')
                            ->label('Barang')
                            ->live()
                            ->getPaginatedOptionsUsing(function (int $offset, int $limit, ?string $search): array {
                                /** @var Delivery $delivery */
                                $delivery = $this->getOwnerRecord();

                                $query = Item::query()
                                    ->where('is_stockable', true)
                                    ->where('has_variants', false)
                                    ->whereIn('id', InventoryBalance::query()
                                        ->where('warehouse_id', $delivery->warehouse_id)
                                        ->where('quantity_on_hand', '>', 0)
                                        ->select('item_id'))
                                    ->whereNotIn('id', $delivery->lines()->select('item_id'))
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
                            ->helperText('Hanya barang dengan stok tersedia di gudang asal.')
                            ->required(),
                        TextEntry::make('available_quantity')
                            ->label('Stok tersedia')
                            ->state(fn (Get $get): string => $this->availableStockFor($get('item_id')))
                            ->helperText(fn (): string => 'Stok saat ini di '
                                .($this->getOwnerRecord()->warehouse?->name ?? 'gudang asal').'.'),
                        TextInput::make('quantity')
                            ->label('Jumlah')
                            ->numeric()
                            ->default(1)
                            ->minValue(0.001)
                            ->required(),
                        Textarea::make('note')
                            ->label('Catatan')
                            ->rows(2),
                    ])
                    ->successNotificationTitle('Barang ditambahkan'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Ubah')
                    ->visible(fn (): bool => $this->ownerIsDraft())
                    ->modalHeading('Ubah barang pengiriman')
                    ->modalDescription(fn (DeliveryLine $record): string => $record->item
                        ? "{$record->item->item_code} — {$record->item->item_name}"
                        : '—')
                    ->schema([
                        TextInput::make('quantity')
                            ->label('Jumlah')
                            ->numeric()
                            ->minValue(0.001)
                            ->required(),
                        Textarea::make('note')
                            ->label('Catatan')
                            ->rows(2),
                    ]),
                DeleteAction::make()
                    ->visible(fn (): bool => $this->ownerIsDraft()),
            ])
            ->defaultSort('id')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Belum ada barang')
            ->emptyStateDescription('Tambahkan barang yang akan dikirim dari gudang asal.');
    }

    private function ownerIsDraft(): bool
    {
        /** @var Delivery $delivery */
        $delivery = $this->getOwnerRecord();

        return $delivery->isDraft();
    }

    private function availableStockFor(mixed $itemId): string
    {
        if (blank($itemId)) {
            return '—';
        }

        return Number::format($this->balanceFor((string) $itemId), maxPrecision: 3, locale: app()->getLocale());
    }

    private function balanceFor(?string $itemId): float
    {
        if (blank($itemId)) {
            return 0.0;
        }

        if ($this->balances === null) {
            $this->balances = InventoryBalance::query()
                ->where('warehouse_id', $this->getOwnerRecord()->warehouse_id)
                ->pluck('quantity_on_hand', 'item_id');
        }

        return (float) ($this->balances[$itemId] ?? 0);
    }
}
