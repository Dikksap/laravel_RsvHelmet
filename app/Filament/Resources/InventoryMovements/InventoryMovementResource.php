<?php

namespace App\Filament\Resources\InventoryMovements;

use App\Filament\Forms\Components\InfiniteSelect;
use App\Filament\Resources\InventoryMovements\Pages\CreateInventoryMovement;
use App\Filament\Resources\InventoryMovements\Pages\ListInventoryMovements;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\ItemComponent;
use App\Models\ProductionBatch;
use App\Models\Warehouse;
use App\Services\StockMovementService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static string|UnitEnum|null $navigationGroup = 'Gudang';

    protected static ?string $navigationLabel = 'Transaksi Stok';

    protected static ?string $modelLabel = 'Transaksi Stok';

    protected static ?string $pluralModelLabel = 'Transaksi Stok';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail transaksi')
                    ->columns(2)
                    ->schema([
                        Select::make('type')
                            ->label('Jenis transaksi')
                            ->options(InventoryMovement::formTypeOptions())
                            ->required()
                            ->live(),
                        DateTimePicker::make('occurred_at')
                            ->label('Tanggal & waktu')
                            ->default(now())
                            ->required(),
                        Select::make('warehouse_id')
                            ->label(fn (Get $get): string => $get('type') === InventoryMovement::TYPE_TRANSFER
                                ? 'Gudang asal'
                                : 'Gudang')
                            ->relationship('warehouse', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),
                        Select::make('destination_warehouse_id')
                            ->label('Gudang tujuan')
                            ->options(fn (): array => Warehouse::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->required(fn (Get $get): bool => $get('type') === InventoryMovement::TYPE_TRANSFER)
                            ->visible(fn (Get $get): bool => $get('type') === InventoryMovement::TYPE_TRANSFER)
                            ->searchable()
                            ->helperText('Stok akan dikurangi dari gudang asal dan ditambahkan ke gudang tujuan.'),
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
                            ->live(),
                        Select::make('production_batch_id')
                            ->label('Batch produksi')
                            ->relationship('productionBatch', 'batch_code')
                            ->searchable()
                            ->preload()
                            ->placeholder('Tidak terkait batch')
                            ->live()
                            ->helperText('Untuk hasil produksi, pilih batch agar material otomatis dipotong sesuai komposisi.'),
                        TextInput::make('quantity')
                            ->label('Jumlah')
                            ->numeric()
                            ->minValue(0.001)
                            ->required()
                            ->live(onBlur: true)
                            ->helperText('Jumlah barang yang masuk atau keluar.'),
                        Toggle::make('consume_components')
                            ->label('Potong material sesuai komposisi')
                            ->helperText('Material aktif pada komposisi barang akan otomatis dikurangi dari gudang material batch.')
                            ->default(true)
                            ->visible(fn (Get $get): bool => $get('type') === InventoryMovement::TYPE_IN_PRODUCTION
                                && filled($get('production_batch_id'))),
                        TextEntry::make('component_consumption_preview')
                            ->label('Material yang akan dipotong')
                            ->visible(fn (Get $get): bool => $get('type') === InventoryMovement::TYPE_IN_PRODUCTION
                                && filled($get('item_id')))
                            ->html()
                            ->columnSpanFull()
                            ->state(fn (Get $get): HtmlString => self::componentConsumptionPreview($get)),
                    ]),
                Section::make('Keterangan')
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        TextInput::make('reference')
                            ->label('No. referensi')
                            ->maxLength(100)
                            ->helperText('Contoh: nomor pembelian, surat jalan, atau nomor dokumen lain.'),
                        Textarea::make('note')
                            ->label('Catatan')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Waktu')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => InventoryMovement::typeOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match (true) {
                        in_array($state, InventoryMovement::incomingTypes(), true) => 'success',
                        default => 'danger',
                    }),
                TextColumn::make('item.item_code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('item.item_name')
                    ->label('Barang')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Masuk')
                    ->state(fn (InventoryMovement $record): ?string => $record->isIncoming()
                        ? Number::format((float) $record->quantity, maxPrecision: 3, locale: app()->getLocale())
                        : null)
                    ->color('success'),
                TextColumn::make('quantity_out')
                    ->label('Keluar')
                    ->state(fn (InventoryMovement $record): ?string => $record->isIncoming()
                        ? null
                        : Number::format((float) $record->quantity, maxPrecision: 3, locale: app()->getLocale()))
                    ->color('danger'),
                TextColumn::make('productionBatch.batch_code')
                    ->label('Batch')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('reference')
                    ->label('Referensi')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('note')
                    ->label('Catatan')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creator.name')
                    ->label('Dicatat oleh')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Jenis transaksi')
                    ->options(InventoryMovement::typeOptions()),
                SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->relationship('warehouse', 'name'),
                SelectFilter::make('item_id')
                    ->label('Barang')
                    ->relationship('item', 'item_name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('production_batch_id')
                    ->label('Batch produksi')
                    ->relationship('productionBatch', 'batch_code')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Hapus')
                    ->modalHeading('Hapus transaksi?')
                    ->modalDescription('Saldo stok akan dikembalikan seperti sebelum transaksi ini dicatat. Bila transaksi bagian dari pindah gudang, kedua baris (keluar & masuk) ikut terhapus.')
                    ->successNotificationTitle('Transaksi dihapus')
                    ->using(function (InventoryMovement $record): bool {
                        try {
                            app(StockMovementService::class)->delete($record);
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->danger()
                                ->title('Transaksi tidak bisa dihapus')
                                ->body(collect($exception->errors())->flatten()->first())
                                ->persistent()
                                ->send();

                            throw new Halt;
                        }

                        return true;
                    }),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->infinite(perPage: 50);
    }

    private static function componentConsumptionPreview(Get $get): HtmlString
    {
        $item = Item::query()
            ->with('components.componentItem')
            ->find($get('item_id'));

        if (! $item) {
            return new HtmlString('Pilih barang untuk melihat material.');
        }

        $components = $item->components->where('is_active', true);

        if ($components->isEmpty()) {
            return new HtmlString('Barang ini belum memiliki komposisi aktif, sehingga tidak ada material yang dipotong.');
        }

        $batchId = $get('production_batch_id');

        if (blank($batchId)) {
            return new HtmlString('Pilih batch produksi agar material bisa dipotong otomatis.');
        }

        $quantity = (float) $get('quantity');

        if ($quantity <= 0) {
            return new HtmlString('Isi jumlah hasil produksi untuk melihat kebutuhan material.');
        }

        $batch = ProductionBatch::query()
            ->with('materialWarehouse')
            ->find($batchId);

        $warehouseId = $batch?->material_warehouse_id ?? $get('warehouse_id');
        $warehouseName = $batch?->materialWarehouse?->name
            ?? Warehouse::query()->find($warehouseId)?->name
            ?? 'gudang transaksi';

        $rows = $components
            ->map(function (ItemComponent $component) use ($quantity, $warehouseId): string {
                $required = round((float) $component->quantity * $quantity, 3);

                $onHand = (float) InventoryBalance::query()
                    ->where('item_id', $component->component_item_id)
                    ->where('warehouse_id', $warehouseId)
                    ->value('quantity_on_hand');

                $name = e(($component->componentItem?->item_code ?? '—').' — '.($component->componentItem?->item_name ?? ''));
                $requiredText = Number::format($required, maxPrecision: 3, locale: app()->getLocale());
                $onHandText = Number::format($onHand, maxPrecision: 3, locale: app()->getLocale());

                $status = $onHand < $required
                    ? ' · <span style="color:#dc2626;font-weight:600">kurang '.Number::format($required - $onHand, maxPrecision: 3, locale: app()->getLocale()).'</span>'
                    : ' · <span style="color:#16a34a">cukup</span>';

                return '<li style="margin-bottom:0.25rem">'.$name.': butuh <strong>'.$requiredText.'</strong> · stok '.$onHandText.$status.'</li>';
            })
            ->implode('');

        return new HtmlString(
            '<div style="font-size:0.875rem">Gudang material: <strong>'.e($warehouseName).'</strong>'
            .'<ul style="margin-top:0.5rem;list-style:disc;padding-left:1.25rem">'.$rows.'</ul></div>'
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryMovements::route('/'),
            'create' => CreateInventoryMovement::route('/create'),
        ];
    }
}
