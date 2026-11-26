<?php

namespace App\Filament\Resources\InventoryMovements;

use App\Filament\Forms\Components\InfiniteSelect;
use App\Filament\Resources\InventoryMovements\Pages\CreateInventoryMovement;
use App\Filament\Resources\InventoryMovements\Pages\ListInventoryMovements;
use App\Models\ExternalItemMapping;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\ItemComponent;
use App\Models\ProductionBatch;
use App\Models\Warehouse;
use App\Services\ExternalBarangApiService;
use App\Services\ExternalBarangSyncService;
use App\Services\StockMovementService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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

    protected static ?int $navigationSort = 30;

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
                        Toggle::make('source_external')
                            ->label('Ambil hasil produksi dari API eksternal')
                            ->helperText('Jumlah unit dihitung dari data unit API sesuai varian, status, dan periode yang dipilih.')
                            ->default(true)
                            ->live()
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => $get('type') === InventoryMovement::TYPE_IN_PRODUCTION),
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
                            ->live()
                            ->visible(fn (Get $get): bool => ! self::isExternalMode($get)),
                        Select::make('production_batch_id')
                            ->label('Batch produksi')
                            ->relationship(
                                'productionBatch',
                                'batch_code',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->where('status', '!=', ProductionBatch::STATUS_CANCELLED),
                            )
                            ->getOptionLabelFromRecordUsing(fn (ProductionBatch $record): string => "{$record->batch_code} ({$record->status_label})")
                            ->searchable()
                            ->preload()
                            ->placeholder('Tidak terkait batch')
                            ->live()
                            ->required(fn (Get $get): bool => self::isExternalMode($get))
                            ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                                if (! self::isExternalMode($get)) {
                                    return;
                                }

                                $warehouseId = ProductionBatch::query()->find($state)?->output_warehouse_id;

                                if (filled($warehouseId)) {
                                    $set('warehouse_id', $warehouseId);
                                }
                            })
                            ->helperText(fn (Get $get): string => self::isExternalMode($get)
                                ? 'Wajib dipilih. Semua unit yang ditarik akan dicatat sebagai hasil produksi batch ini.'
                                : 'Untuk hasil produksi, pilih batch agar material otomatis dipotong sesuai komposisi.'),
                        TextInput::make('external_batch_code')
                            ->label('Kode batch eksternal')
                            ->placeholder('Contoh: BC001')
                            ->live(debounce: 500)
                            ->visible(fn (Get $get): bool => self::isExternalMode($get))
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::refreshExternalQuantities($get, $set))
                            ->helperText('Isi untuk menarik seluruh varian dalam batch ini sekaligus. Kosongkan bila ingin menarik per varian.'),
                        Select::make('external_variant_id')
                            ->label('Varian eksternal')
                            ->options(fn (): array => ExternalItemMapping::query()
                                ->where('is_active', true)
                                ->whereHas('item')
                                ->with('item')
                                ->orderBy('external_variant_code')
                                ->get()
                                ->mapWithKeys(fn (ExternalItemMapping $mapping): array => [
                                    $mapping->external_variant_id => trim("{$mapping->external_variant_code} — {$mapping->item->item_code} {$mapping->item->item_name}"),
                                ])
                                ->all())
                            ->searchable()
                            ->live()
                            ->required(fn (Get $get): bool => self::isExternalMode($get) && blank($get('external_batch_code')))
                            ->visible(fn (Get $get): bool => self::isExternalMode($get))
                            ->helperText(fn (Get $get): string => blank($get('external_batch_code'))
                                ? 'Hanya varian yang sudah dipetakan ke barang RSV.'
                                : 'Diabaikan bila kode batch eksternal diisi.'),
                        TextInput::make('external_quantity')
                            ->label('Jumlah unit')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->live()
                            ->visible(fn (Get $get): bool => self::isExternalMode($get) && blank($get('external_batch_code')))
                            ->helperText('Kosongkan untuk mengambil semua unit yang tersedia.'),
                        DatePicker::make('tanggal_awal')
                            ->label('Tanggal awal produksi')
                            ->placeholder('Tanpa batas awal')
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::refreshExternalQuantities($get, $set))
                            ->visible(fn (Get $get): bool => self::isExternalMode($get)),
                        DatePicker::make('tanggal_akhir')
                            ->label('Tanggal akhir produksi')
                            ->placeholder('Tanpa batas akhir')
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::refreshExternalQuantities($get, $set))
                            ->visible(fn (Get $get): bool => self::isExternalMode($get)),
                        Select::make('external_status')
                            ->label('Status unit eksternal')
                            ->options(fn (ExternalBarangApiService $apiService): array => $apiService->getStatusOptions(forSync: true))
                            ->placeholder('Pilih status dari API eksternal')
                            ->helperText('Jika daftar status kosong, tidak ada status yang dapat disinkronkan saat ini.')
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::refreshExternalQuantities($get, $set))
                            ->required(fn (Get $get): bool => self::isExternalMode($get))
                            ->visible(fn (Get $get): bool => self::isExternalMode($get)),
                        Repeater::make('external_quantities')
                            ->label('Jumlah unit per varian')
                            ->schema([
                                Hidden::make('external_variant_id'),
                                Hidden::make('max_quantity'),
                                TextInput::make('item_label')
                                    ->label('Varian / Barang')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('quantity')
                                    ->label('Jumlah unit')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(fn (Get $get): int => (int) $get('max_quantity'))
                                    ->required(),
                            ])
                            ->columns(2)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => self::isExternalMode($get) && filled($get('external_batch_code')))
                            ->helperText('Ubah jumlah bila tidak semua unit ingin dimasukkan. Nilai 0 akan melewati varian tersebut.'),
                        TextEntry::make('external_preview')
                            ->label('Ringkasan hasil produksi')
                            ->visible(fn (Get $get): bool => self::isExternalMode($get))
                            ->html()
                            ->columnSpanFull()
                            ->state(fn (Get $get): HtmlString => self::externalPreview($get)),
                        TextInput::make('quantity')
                            ->label('Jumlah')
                            ->numeric()
                            ->minValue(0.001)
                            ->required()
                            ->live(onBlur: true)
                            ->visible(fn (Get $get): bool => ! self::isExternalMode($get))
                            ->helperText('Jumlah barang yang masuk atau keluar.'),
                        Toggle::make('consume_components')
                            ->label('Potong material sesuai komposisi')
                            ->helperText('Material aktif pada komposisi barang akan otomatis dikurangi dari gudang material batch.')
                            ->default(true)
                            ->visible(fn (Get $get): bool => $get('type') === InventoryMovement::TYPE_IN_PRODUCTION
                                && filled($get('production_batch_id'))
                                && ! self::isExternalMode($get)),
                        TextEntry::make('component_consumption_preview')
                            ->label('Material yang akan dipotong')
                            ->visible(fn (Get $get): bool => $get('type') === InventoryMovement::TYPE_IN_PRODUCTION
                                && filled($get('item_id'))
                                && ! self::isExternalMode($get))
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

    private static function isExternalMode(Get $get): bool
    {
        return $get('type') === InventoryMovement::TYPE_IN_PRODUCTION
            && (bool) $get('source_external');
    }

    private static function refreshExternalQuantities(Get $get, Set $set): void
    {
        $batchCode = trim((string) $get('external_batch_code'));
        $status = $get('external_status');

        if ($batchCode === '' || blank($status)) {
            $set('external_quantities', []);

            return;
        }

        $preview = app(ExternalBarangSyncService::class)->previewBatchOutput(
            $batchCode,
            filled($get('tanggal_awal')) ? (string) $get('tanggal_awal') : null,
            filled($get('tanggal_akhir')) ? (string) $get('tanggal_akhir') : null,
            $status,
        );

        $set('external_quantities', collect($preview['groups'])
            ->map(fn (array $group): array => [
                'external_variant_id' => $group['external_variant_id'],
                'max_quantity' => count($group['units']),
                'item_label' => "{$group['external_variant_code']} — {$group['item_code']} {$group['item_name']}",
                'quantity' => count($group['units']),
            ])
            ->values()
            ->all());
    }

    private static function externalPreview(Get $get): HtmlString
    {
        $status = $get('external_status');

        if (blank($status)) {
            return new HtmlString('Pilih status unit eksternal untuk melihat ringkasan unit dari API.');
        }

        $batchCode = trim((string) $get('external_batch_code'));

        if ($batchCode !== '') {
            return self::externalBatchPreview($get, $batchCode, $status);
        }

        $variantId = (int) $get('external_variant_id');

        if ($variantId <= 0) {
            return new HtmlString('Pilih varian eksternal untuk melihat ringkasan unit dari API.');
        }

        $mapping = ExternalItemMapping::query()
            ->where('is_active', true)
            ->where('external_variant_id', $variantId)
            ->with('item')
            ->first();

        if (! $mapping?->item) {
            return new HtmlString('<span style="color:#dc2626;font-weight:600">Varian ini belum dipetakan ke barang RSV.</span>');
        }

        $count = app(ExternalBarangSyncService::class)->countEligibleUnits(
            $variantId,
            filled($get('tanggal_awal')) ? (string) $get('tanggal_awal') : null,
            filled($get('tanggal_akhir')) ? (string) $get('tanggal_akhir') : null,
            $status,
        );

        $itemLabel = e("{$mapping->item->item_code} — {$mapping->item->item_name}");
        $warning = $count === 0
            ? ' <span style="color:#dc2626;font-weight:600">Tidak ada unit baru untuk dicatat.</span>'
            : '';

        return new HtmlString(
            '<div style="font-size:0.875rem">Barang RSV: <strong>'.$itemLabel.'</strong>'
            .'<br>Unit siap masuk: <strong>'.$count.'</strong> (status '.e($status).')'.$warning.'</div>'
        );
    }

    private static function externalBatchPreview(Get $get, string $batchCode, string $status): HtmlString
    {
        $preview = app(ExternalBarangSyncService::class)->previewBatchOutput(
            $batchCode,
            filled($get('tanggal_awal')) ? (string) $get('tanggal_awal') : null,
            filled($get('tanggal_akhir')) ? (string) $get('tanggal_akhir') : null,
            $status,
        );

        if ($preview['total_units'] === 0) {
            return new HtmlString('<span style="color:#dc2626;font-weight:600">Tidak ada unit baru untuk kode batch '.e($batchCode).'.</span>');
        }

        $unmapped = collect($preview['unmapped'])
            ->map(fn (array $variant): string => e($variant['external_variant_code'] ?? '#'.$variant['external_variant_id'])
                .' ('.$variant['unit_count'].' unit)')
            ->implode(', ');

        return new HtmlString(
            '<div style="font-size:0.875rem">Kode batch eksternal: <strong>'.e($batchCode).'</strong>'
            .'<br>Total unit siap masuk: <strong>'.$preview['total_units'].'</strong>'
            .($unmapped !== ''
                ? '<div style="color:#b45309">Varian belum dipetakan (dilewati): '.$unmapped.'</div>'
                : '')
            .'</div>'
        );
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
