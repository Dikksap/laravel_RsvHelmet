<?php

namespace App\Filament\Resources\ItemRequests\RelationManagers;

use App\Filament\Forms\Components\InfiniteSelect;
use App\Models\Item;
use App\Models\ItemRequestLine;
use Filament\Actions\EditAction;
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
                    ->label('Jumlah diminta')
                    ->numeric(maxDecimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('approval_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ItemRequestLine::approvalStatusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        ItemRequestLine::APPROVAL_APPROVED => 'success',
                        ItemRequestLine::APPROVAL_REJECTED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('approved_quantity')
                    ->label('Jumlah disetujui')
                    ->numeric(maxDecimalPlaces: 3)
                    ->placeholder('—'),
                TextColumn::make('rejection_note')
                    ->label('Alasan penolakan')
                    ->placeholder('—')
                    ->wrap(),
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
                        InfiniteSelect::make('item_id')
                            ->label('Barang')
                            ->getPaginatedOptionsUsing(function (int $offset, int $limit, ?string $search): array {
                                $query = Item::query()
                                    ->when(filled($search), fn (Builder $query): Builder => $query->where(
                                        fn (Builder $query): Builder => $query
                                            ->where('item_code', 'like', "%{$search}%")
                                            ->orWhere('item_name', 'like', "%{$search}%"),
                                    ));

                                $rows = $query->orderBy('item_name')->skip($offset)->take($limit + 1)->get();

                                return [
                                    'options' => $rows->take($limit)
                                        ->mapWithKeys(fn (Item $item): array => [$item->getKey() => $item->item_name])
                                        ->all(),
                                    'hasMore' => $rows->count() > $limit,
                                ];
                            })
                            ->getOptionLabelUsing(fn ($value): ?string => Item::query()->find($value)?->item_name)
                            ->required(),
                    ]),
            ])
            ->defaultSort('id');
    }
}
