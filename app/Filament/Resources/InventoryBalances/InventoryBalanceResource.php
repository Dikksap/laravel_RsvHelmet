<?php

namespace App\Filament\Resources\InventoryBalances;

use App\Filament\Resources\InventoryBalances\Pages\CreateInventoryBalance;
use App\Filament\Resources\InventoryBalances\Pages\EditInventoryBalance;
use App\Filament\Resources\InventoryBalances\Pages\ListInventoryBalances;
use App\Models\InventoryBalance;
use App\Models\Item;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use UnitEnum;

class InventoryBalanceResource extends Resource
{
    protected static ?string $model = InventoryBalance::class;

    protected static string|UnitEnum|null $navigationGroup = 'Gudang';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Stok Gudang';

    protected static ?string $modelLabel = 'Stok Gudang';

    protected static ?string $pluralModelLabel = 'Stok Gudang';

    protected static ?int $navigationSort = 10;

    public static function getNavigationBadge(): ?string
    {
        $count = InventoryBalance::query()->lowStock()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Jumlah baris stok yang sudah mencapai atau di bawah stok minimum.';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi barang')
                    ->columns(2)
                    ->schema([
                        Select::make('warehouse_id')
                            ->label('Gudang')
                            ->relationship('warehouse', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('item_id')
                            ->label('Barang')
                            ->relationship(
                                name: 'item',
                                titleAttribute: 'item_name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->where('is_stockable', true)
                                    ->where('has_variants', false),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Item $record): string => "{$record->item_code} — {$record->item_name}")
                            ->required()
                            ->searchable(['item_code', 'item_name'])
                            ->preload()
                            ->rule(fn (Get $get, ?InventoryBalance $record) => Rule::unique('inventory_balances', 'item_id')
                                ->where('warehouse_id', $get('warehouse_id'))
                                ->ignore($record?->getKey()))
                            ->validationMessages([
                                'unique' => 'Barang ini sudah memiliki stok di gudang yang dipilih.',
                            ]),
                    ]),
                Section::make('Jumlah stok')
                    ->columns(3)
                    ->schema([
                        TextInput::make('quantity_on_hand')
                            ->label('Stok')
                            ->helperText('Jumlah barang yang ada di gudang ini.')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required()
                            ->formatStateUsing(fn (mixed $state): ?string => self::trimTrailingZeros($state))
                            ->live(onBlur: true),
                        TextInput::make('minimum_quantity')
                            ->label('Stok minimum')
                            ->helperText('Sistem menandai stok menipis bila stok kurang dari atau sama dengan angka ini.')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required()
                            ->formatStateUsing(fn (mixed $state): ?string => self::trimTrailingZeros($state)),
                        DateTimePicker::make('last_counted_at')
                            ->label('Terakhir dihitung'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
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
                TextColumn::make('last_counted_at')
                    ->label('Terakhir dihitung')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->relationship('warehouse', 'name'),
                SelectFilter::make('stock_status')
                    ->label('Status')
                    ->options([
                        'out' => 'Habis',
                        'low' => 'Menipis',
                        'ok' => 'Aman',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'out' => $query->where('quantity_on_hand', '<=', 0),
                            'low' => $query
                                ->where('quantity_on_hand', '>', 0)
                                ->whereColumn('quantity_on_hand', '<=', 'minimum_quantity'),
                            'ok' => $query->whereColumn('quantity_on_hand', '>', 'minimum_quantity'),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('warehouse_id');
    }

    private static function trimTrailingZeros(mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        $value = (string) $state;

        if (! str_contains($value, '.')) {
            return $value;
        }

        return rtrim(rtrim($value, '0'), '.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryBalances::route('/'),
            'create' => CreateInventoryBalance::route('/create'),
            'edit' => EditInventoryBalance::route('/{record}/edit'),
        ];
    }
}
