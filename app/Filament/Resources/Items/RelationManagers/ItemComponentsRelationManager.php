<?php

namespace App\Filament\Resources\Items\RelationManagers;

use App\Models\Item;
use App\Models\ItemComponent;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use MrPunyapal\FilamentInfiniteSelect\InfiniteSelect;

class ItemComponentsRelationManager extends RelationManager
{
    protected static string $relationship = 'components';

    protected static ?string $title = 'Komposisi barang';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                InfiniteSelect::make('component_item_id')
                    ->label('Barang komponen')
                    ->getPaginatedOptionsUsing(function (int $offset, int $limit, ?string $search): array {
                        $query = Item::query()
                            ->where('is_stockable', true)
                            ->where('has_variants', false)
                            ->whereKeyNot($this->getOwnerRecord()->getKey())
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
                    ->rule(fn (?ItemComponent $record) => Rule::unique('item_components', 'component_item_id')
                        ->where('parent_item_id', $this->getOwnerRecord()->getKey())
                        ->ignore($record?->getKey()))
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (blank($value)) {
                            return;
                        }

                        if (ItemComponent::wouldCreateCycle($this->getOwnerRecord()->getKey(), (string) $value)) {
                            $fail('Komposisi ini membuat hubungan melingkar antar barang.');
                        }
                    })
                    ->validationMessages([
                        'unique' => 'Barang ini sudah ada di komposisi.',
                    ]),
                TextInput::make('quantity')
                    ->label('Jumlah per 1 unit')
                    ->numeric()
                    ->minValue(0.001)
                    ->default(1)
                    ->required()
                    ->helperText('Berapa unit komponen ini dibutuhkan untuk membuat 1 unit barang ini.'),
                Textarea::make('note')
                    ->label('Catatan')
                    ->rows(2),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('componentItem.item_code')
                    ->label('Kode')
                    ->searchable(),
                TextColumn::make('componentItem.item_name')
                    ->label('Komponen')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('quantity')
                    ->label('Jumlah per unit')
                    ->numeric(maxDecimalPlaces: 3),
                TextColumn::make('note')
                    ->label('Catatan')
                    ->placeholder('—')
                    ->limit(40),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah komponen'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
