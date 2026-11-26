<?php

namespace App\Filament\Resources\Items;

use App\Filament\Exports\ItemExporter;
use App\Filament\Forms\Components\InfiniteSelect;
use App\Filament\Pages\BomBuilder;
use App\Filament\Resources\Items\Pages\CreateItem;
use App\Filament\Resources\Items\Pages\EditItem;
use App\Filament\Resources\Items\Pages\ListItems;
use App\Filament\Resources\Items\RelationManagers\InventoryBalancesRelationManager;
use App\Filament\Resources\Items\RelationManagers\ItemComponentsRelationManager;
use App\Filament\Resources\Items\RelationManagers\VariantMappingsRelationManager;
use App\Models\Brand;
use App\Models\Division;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemType;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use UnitEnum;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'Barang';

    protected static ?string $modelLabel = 'Barang';

    protected static ?string $pluralModelLabel = 'Barang';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'item_name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas')
                    ->columns(2)
                    ->schema([
                        TextInput::make('item_code')
                            ->label('Kode barang')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
                        TextInput::make('barcode')
                            ->label('Barcode')
                            ->maxLength(100)
                            ->unique(ignoreRecord: true),
                        TextInput::make('item_name')
                            ->label('Nama barang')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(3)
                            ->columnSpanFull(),
                        FileUpload::make('image_path')
                            ->label('Gambar barang')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('items')
                            ->visibility('public')
                            ->maxSize(10240)
                            ->helperText('Format JPG, PNG, atau WebP. Maksimal 10 MB.')
                            ->columnSpanFull(),
                        Select::make('item_type_id')
                            ->label('Jenis barang')
                            ->relationship('type', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('base_uom_id')
                            ->label('Satuan dasar')
                            ->relationship('baseUom', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('category_id')
                            ->label('Kategori')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('brand_id')
                            ->label('Merek')
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('division_id')
                            ->label('Divisi')
                            ->relationship('division', 'name')
                            ->searchable()
                            ->preload(),
                    ]),
                Section::make('Varian')
                    ->columns(2)
                    ->schema([
                        Toggle::make('has_variants')
                            ->label('Barang induk memiliki varian'),
                        InfiniteSelect::make('parent_item_id')
                            ->label('Barang induk')
                            ->getPaginatedOptionsUsing(function (int $offset, int $limit, ?string $search, ?Item $record): array {
                                $query = Item::query()
                                    ->when(filled($search), fn (Builder $query): Builder => $query->where(
                                        fn (Builder $query): Builder => $query
                                            ->where('item_code', 'like', "%{$search}%")
                                            ->orWhere('item_name', 'like', "%{$search}%"),
                                    ))
                                    ->when($record, fn (Builder $query): Builder => $query->whereKeyNot($record->getKey()));

                                $rows = $query->orderBy('item_name')->skip($offset)->take($limit + 1)->get();

                                return [
                                    'options' => $rows->take($limit)
                                        ->mapWithKeys(fn (Item $item): array => [$item->getKey() => $item->item_name])
                                        ->all(),
                                    'hasMore' => $rows->count() > $limit,
                                ];
                            })
                            ->getOptionLabelUsing(fn ($value): ?string => Item::query()->find($value)?->item_name)
                            ->helperText('Kosongkan jika ini barang induk/master.'),
                    ]),
                Section::make('Dimensi & berat')
                    ->columns(3)
                    ->schema([
                        TextInput::make('weight')
                            ->label('Berat')
                            ->numeric()
                            ->default(0),
                        Select::make('weight_uom_id')
                            ->label('Satuan berat')
                            ->relationship('weightUom', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('length')
                            ->label('Panjang')
                            ->numeric()
                            ->default(0),
                        TextInput::make('width')
                            ->label('Lebar')
                            ->numeric()
                            ->default(0),
                        TextInput::make('height')
                            ->label('Tinggi')
                            ->numeric()
                            ->default(0),
                        Select::make('dimension_uom_id')
                            ->label('Satuan dimensi')
                            ->relationship('dimensionUom', 'name')
                            ->searchable()
                            ->preload(),
                    ]),
                Section::make('Harga')
                    ->columns(2)
                    ->schema([
                        TextInput::make('base_price')
                            ->label('Harga dasar')
                            ->numeric()
                            ->default(0),
                        TextInput::make('standard_cost')
                            ->label('Harga pokok standar')
                            ->numeric()
                            ->default(0),
                    ]),
                Section::make('Perilaku')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_stockable')
                            ->label('Bisa distok')
                            ->default(true),
                        Toggle::make('is_sellable')
                            ->label('Bisa dijual')
                            ->default(true),
                        Toggle::make('is_purchasable')
                            ->label('Bisa dibeli')
                            ->default(true),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Gambar')
                    ->disk('public')
                    ->visibility('public')
                    ->square()
                    ->size(40),
                TextColumn::make('item_code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('item_name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('type.name')
                    ->label('Jenis')
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('brand.name')
                    ->label('Merek')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('division.name')
                    ->label('Divisi')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('baseUom.code')
                    ->label('Satuan'),
                TextColumn::make('base_price')
                    ->label('Harga dasar')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2))
                    ->sortable()
                    ->alignEnd(),
                IconColumn::make('has_variants')
                    ->label('Punya varian')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('parent.item_name')
                    ->label('Induk')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('item_type_id')
                    ->label('Jenis barang')
                    ->relationship('type', 'name'),
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name'),
                SelectFilter::make('brand_id')
                    ->label('Merek')
                    ->relationship('brand', 'name'),
                SelectFilter::make('division_id')
                    ->label('Divisi')
                    ->relationship('division', 'name'),
                TernaryFilter::make('has_variants')
                    ->label('Punya varian'),
                TernaryFilter::make('is_active')
                    ->label('Aktif'),
            ])
            ->recordActions([
                Action::make('composition')
                    ->label('Komposisi')
                    ->icon('heroicon-o-squares-2x2')
                    ->iconButton()
                    ->url(fn (Item $record): string => BomBuilder::getUrl(['item' => $record->getKey()]))
                    ->visible(fn (Item $record): bool => $record->is_stockable && ! $record->has_variants),
                EditAction::make()
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::bulkEditMasterDataAction(),
                    ExportBulkAction::make()
                        ->exporter(ItemExporter::class),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('item_name')
            ->infinite(perPage: 50);
    }

    public static function bulkEditMasterDataAction(): BulkAction
    {
        return BulkAction::make('bulkEditMasterData')
            ->label('Edit massal')
            ->icon('heroicon-o-pencil-square')
            ->modalHeading('Edit massal data master')
            ->modalDescription('Centang data master yang ingin diubah. Data master yang tidak dicentang dibiarkan apa adanya.')
            ->modalSubmitActionLabel('Simpan perubahan')
            ->schema([
                Section::make('Data master')
                    ->columns(2)
                    ->schema([
                        Toggle::make('change_item_type')
                            ->label('Ubah jenis barang')
                            ->live(),
                        Select::make('item_type_id')
                            ->label('Jenis barang')
                            ->options(fn (): array => ItemType::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(fn (Get $get): bool => (bool) $get('change_item_type'))
                            ->visible(fn (Get $get): bool => (bool) $get('change_item_type')),
                        Toggle::make('change_category')
                            ->label('Ubah kategori')
                            ->live(),
                        Select::make('category_id')
                            ->label('Kategori')
                            ->options(fn (): array => ItemCategory::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->placeholder('Kosongkan kategori')
                            ->helperText('Biarkan kosong untuk menghapus kategori barang.')
                            ->visible(fn (Get $get): bool => (bool) $get('change_category')),
                        Toggle::make('change_brand')
                            ->label('Ubah merek')
                            ->live(),
                        Select::make('brand_id')
                            ->label('Merek')
                            ->options(fn (): array => Brand::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->placeholder('Kosongkan merek')
                            ->helperText('Biarkan kosong untuk menghapus merek barang.')
                            ->visible(fn (Get $get): bool => (bool) $get('change_brand')),
                        Toggle::make('change_division')
                            ->label('Ubah divisi')
                            ->live(),
                        Select::make('division_id')
                            ->label('Divisi')
                            ->options(fn (): array => Division::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->placeholder('Kosongkan divisi')
                            ->helperText('Biarkan kosong untuk menghapus divisi barang.')
                            ->visible(fn (Get $get): bool => (bool) $get('change_division')),
                    ]),
            ])
            ->action(function (EloquentCollection $records, array $data, BulkAction $action): void {
                $updates = [];

                if ($data['change_item_type'] ?? false) {
                    $updates['item_type_id'] = $data['item_type_id'] ?? null;
                }

                if ($data['change_category'] ?? false) {
                    $updates['category_id'] = $data['category_id'] ?: null;
                }

                if ($data['change_brand'] ?? false) {
                    $updates['brand_id'] = $data['brand_id'] ?: null;
                }

                if ($data['change_division'] ?? false) {
                    $updates['division_id'] = $data['division_id'] ?: null;
                }

                if ($updates === []) {
                    Notification::make()
                        ->warning()
                        ->title('Tidak ada data master yang diubah')
                        ->body('Centang minimal satu data master yang ingin diubah.')
                        ->send();

                    $action->halt();

                    return;
                }

                Item::query()
                    ->whereKey($records->modelKeys())
                    ->update($updates);
            })
            ->successNotificationTitle(fn (EloquentCollection $records): string => "{$records->count()} barang diperbarui.")
            ->deselectRecordsAfterCompletion();
    }

    public static function getRelations(): array
    {
        return [
            VariantMappingsRelationManager::class,
            ItemComponentsRelationManager::class,
            InventoryBalancesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItems::route('/'),
            'create' => CreateItem::route('/create'),
            'edit' => EditItem::route('/{record}/edit'),
        ];
    }
}
