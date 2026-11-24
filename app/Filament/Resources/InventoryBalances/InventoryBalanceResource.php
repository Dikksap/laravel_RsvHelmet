<?php

namespace App\Filament\Resources\InventoryBalances;

use App\Filament\Forms\Components\InfiniteSelect;
use App\Filament\Resources\InventoryBalances\Pages\CreateInventoryBalance;
use App\Filament\Resources\InventoryBalances\Pages\EditInventoryBalance;
use App\Filament\Resources\InventoryBalances\Pages\ListInventoryBalances;
use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\Warehouse;
use App\Services\StockMovementService;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
                    self::transferBulkAction(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('warehouse_id')
            ->infinite(perPage: 50);
    }

    private static function transferBulkAction(): BulkAction
    {
        return BulkAction::make('transfer')
            ->label('Pindah gudang')
            ->icon('heroicon-o-arrows-right-left')
            ->color('warning')
            ->modalHeading('Pindah gudang massal')
            ->modalDescription('Stok barang terpilih dipindahkan ke gudang tujuan. Barang dari gudang asal berbeda diproses otomatis per gudang.')
            ->modalSubmitActionLabel('Pindahkan')
            ->schema([
                Section::make('Tujuan pemindahan')
                    ->columns(2)
                    ->schema([
                        Select::make('destination_warehouse_id')
                            ->label('Gudang tujuan')
                            ->options(fn (): array => Warehouse::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->required()
                            ->searchable()
                            ->helperText('Stok dikurangi dari gudang asal masing-masing lalu ditambahkan ke gudang ini.'),
                        DateTimePicker::make('occurred_at')
                            ->label('Tanggal & waktu')
                            ->default(now())
                            ->required(),
                        TextInput::make('reference')
                            ->label('No. referensi')
                            ->maxLength(100)
                            ->placeholder('Otomatis')
                            ->helperText('Kosongkan untuk nomor otomatis. Nomor ini dipakai untuk menandai seluruh barang dalam aksi ini.'),
                        Textarea::make('note')
                            ->label('Catatan')
                            ->rows(3),
                    ]),
                Repeater::make('items')
                    ->label('Barang yang dipindahkan')
                    ->table([
                        TableColumn::make('Kode'),
                        TableColumn::make('Barang'),
                        TableColumn::make('Gudang asal'),
                        TableColumn::make('Stok tersedia'),
                        TableColumn::make('Jumlah'),
                    ])
                    ->schema([
                        Hidden::make('balance_id'),
                        Hidden::make('on_hand'),
                        TextInput::make('item_code')
                            ->hiddenLabel()
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('item_name')
                            ->hiddenLabel()
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('warehouse_name')
                            ->hiddenLabel()
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('on_hand_display')
                            ->hiddenLabel()
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('quantity')
                            ->hiddenLabel()
                            ->numeric()
                            ->minValue(0.001)
                            ->maxValue(fn (Get $get): float => (float) $get('on_hand'))
                            ->required(),
                    ])
                    ->addable(false)
                    ->reorderable(false)
                    ->deletable()
                    ->minItems(1)
                    ->columnSpanFull(),
            ])
            ->fillForm(function (EloquentCollection $records): array {
                $records->loadMissing(['item', 'warehouse']);

                return [
                    'occurred_at' => now(),
                    'items' => $records
                        ->filter(fn (InventoryBalance $balance): bool => (float) $balance->quantity_on_hand > 0)
                        ->sortBy(fn (InventoryBalance $balance): string => ($balance->warehouse?->name ?? '')
                            .' '.($balance->item?->item_code ?? ''))
                        ->map(fn (InventoryBalance $balance): array => [
                            'balance_id' => $balance->getKey(),
                            'on_hand' => (float) $balance->quantity_on_hand,
                            'item_code' => $balance->item?->item_code,
                            'item_name' => $balance->item?->item_name,
                            'warehouse_name' => $balance->warehouse?->name,
                            'on_hand_display' => Number::format((float) $balance->quantity_on_hand, maxPrecision: 3, locale: app()->getLocale()),
                            'quantity' => (float) $balance->quantity_on_hand,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->action(function (EloquentCollection $records, array $data, BulkAction $action): void {
                $balances = $records
                    ->loadMissing('item')
                    ->keyBy(fn (InventoryBalance $balance): string => (string) $balance->getKey());

                try {
                    $items = collect($data['items'] ?? [])
                        ->map(function (array $row) use ($balances): ?array {
                            $balance = $balances->get((string) ($row['balance_id'] ?? ''));
                            $quantity = (float) ($row['quantity'] ?? 0);

                            if (! $balance || $quantity <= 0) {
                                return null;
                            }

                            if ($quantity > (float) $balance->quantity_on_hand) {
                                throw ValidationException::withMessages([
                                    'items' => 'Jumlah untuk '.($balance->item?->item_code ?? $balance->item_id)
                                        .' melebihi stok tersedia '.Number::format((float) $balance->quantity_on_hand, maxPrecision: 3, locale: app()->getLocale()).'.',
                                ]);
                            }

                            return [
                                'warehouse_id' => $balance->warehouse_id,
                                'item_id' => $balance->item_id,
                                'quantity' => $quantity,
                            ];
                        })
                        ->filter()
                        ->values();

                    if ($items->isEmpty()) {
                        throw ValidationException::withMessages([
                            'items' => 'Pilih minimal satu barang dengan jumlah lebih dari 0.',
                        ]);
                    }

                    $destinationId = $data['destination_warehouse_id'] ?? null;

                    if (blank($destinationId)) {
                        throw ValidationException::withMessages([
                            'destination_warehouse_id' => 'Gudang tujuan wajib dipilih.',
                        ]);
                    }

                    if ($items->contains(fn (array $item): bool => (int) $item['warehouse_id'] === (int) $destinationId)) {
                        throw ValidationException::withMessages([
                            'destination_warehouse_id' => 'Gudang tujuan tidak boleh sama dengan gudang asal.',
                        ]);
                    }

                    $reference = filled($data['reference'] ?? null)
                        ? $data['reference']
                        : 'PINDAH-'.now()->format('Ymd-His');

                    $total = DB::transaction(function () use ($items, $data, $destinationId, $reference): int {
                        $service = app(StockMovementService::class);

                        return $items
                            ->groupBy('warehouse_id')
                            ->sum(function (Collection $group, int|string $originId) use ($service, $data, $destinationId, $reference): int {
                                $service->transferMany([
                                    'warehouse_id' => $originId,
                                    'destination_warehouse_id' => $destinationId,
                                    'occurred_at' => $data['occurred_at'] ?? now(),
                                    'reference' => $reference,
                                    'note' => $data['note'] ?? null,
                                    'items' => $group->all(),
                                ]);

                                return $group->count();
                            });
                    });
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Pindah gudang gagal')
                        ->body(collect($exception->errors())->flatten()->first())
                        ->persistent()
                        ->send();

                    $action->halt();
                }

                Notification::make()
                    ->success()
                    ->title('Stok berhasil dipindahkan')
                    ->body("{$total} barang dipindahkan ke gudang tujuan.")
                    ->send();
            });
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
