<?php

namespace App\Filament\Resources\Items;

use App\Filament\Exports\ItemExporter;
use App\Filament\Pages\BomBuilder;
use App\Filament\Resources\Items\Pages\CreateItem;
use App\Filament\Resources\Items\Pages\EditItem;
use App\Filament\Resources\Items\Pages\ListItems;
use App\Filament\Resources\Items\RelationManagers\InventoryBalancesRelationManager;
use App\Filament\Resources\Items\RelationManagers\ItemComponentsRelationManager;
use App\Filament\Resources\Items\RelationManagers\VariantMappingsRelationManager;
use App\Models\Item;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
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
                        Select::make('parent_item_id')
                            ->label('Barang induk')
                            ->relationship('parent', 'item_name', ignoreRecord: true)
                            ->searchable()
                            ->preload()
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
                TextColumn::make('item_code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
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
                    ->sortable(),
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
                    ->url(fn (Item $record): string => BomBuilder::getUrl(['item' => $record->getKey()]))
                    ->visible(fn (Item $record): bool => $record->is_stockable && ! $record->has_variants),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ItemExporter::class),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('item_name');
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
