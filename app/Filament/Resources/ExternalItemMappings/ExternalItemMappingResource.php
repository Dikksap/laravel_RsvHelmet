<?php

namespace App\Filament\Resources\ExternalItemMappings;

use App\Filament\Forms\Components\InfiniteSelect;
use App\Filament\Resources\ExternalItemMappings\Pages\CreateExternalItemMapping;
use App\Filament\Resources\ExternalItemMappings\Pages\EditExternalItemMapping;
use App\Filament\Resources\ExternalItemMappings\Pages\ListExternalItemMappings;
use App\Models\ExternalItemMapping;
use App\Models\Item;
use App\Services\ExternalBarangApiService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ExternalItemMappingResource extends Resource
{
    protected static ?string $model = ExternalItemMapping::class;

    protected static string|UnitEnum|null $navigationGroup = 'Gudang';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Mapping Barang Eksternal';

    protected static ?string $modelLabel = 'Mapping Barang Eksternal';

    protected static ?string $pluralModelLabel = 'Mapping Barang Eksternal';

    protected static ?int $navigationSort = 35;

    protected static ?string $recordTitleAttribute = 'external_variant_code';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Varian dari API Eksternal')
                    ->description('Informasi varian helm fisik yang berasal dari API publik sistem produksi eksternal.')
                    ->columns(2)
                    ->schema([
                        Select::make('quick_select_variant')
                            ->label('Pilih Cepat dari Varian API')
                            ->helperText('Pilih varian untuk mengisi otomatis kolom-kolom di bawah.')
                            ->placeholder('Pilih varian dari API eksternal...')
                            ->dehydrated(false)
                            ->live()
                            ->columnSpanFull()
                            ->options(function (ExternalBarangApiService $apiService): array {
                                $variants = $apiService->fetchAvailableVariants();
                                $options = [];
                                foreach ($variants as $v) {
                                    $desc = implode(' - ', array_filter([
                                        $v['external_variant_code'] ?? null,
                                        $v['external_product_name'] ?? null,
                                        $v['external_style_name'] ?? null,
                                        $v['external_color_name'] ?? null,
                                        $v['external_size_name'] ?? null,
                                    ]));
                                    $options[$v['external_variant_id']] = "[ID: {$v['external_variant_id']}] {$desc}";
                                }

                                return $options;
                            })
                            ->afterStateUpdated(function ($state, Set $set, ExternalBarangApiService $apiService): void {
                                if (! $state) {
                                    return;
                                }

                                $variants = $apiService->fetchAvailableVariants();
                                foreach ($variants as $v) {
                                    if ((int) $v['external_variant_id'] === (int) $state) {
                                        $set('external_variant_id', $v['external_variant_id']);
                                        $set('external_variant_code', $v['external_variant_code']);
                                        $set('external_product_name', $v['external_product_name']);
                                        $set('external_style_name', $v['external_style_name']);
                                        $set('external_color_name', $v['external_color_name']);
                                        $set('external_size_name', $v['external_size_name']);
                                        break;
                                    }
                                }
                            }),

                        TextInput::make('external_variant_id')
                            ->label('ID Varian Eksternal')
                            ->helperText('ID numerik varian pada API (variantId).')
                            ->numeric()
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('external_variant_code')
                            ->label('Kode Varian Eksternal')
                            ->helperText('Contoh: WB005, WB029.')
                            ->maxLength(50),
                        TextInput::make('external_product_name')
                            ->label('Nama Produk')
                            ->helperText('Contoh: Windbreaker.'),
                        TextInput::make('external_style_name')
                            ->label('Style')
                            ->helperText('Contoh: Motif, Solid.'),
                        TextInput::make('external_color_name')
                            ->label('Warna')
                            ->helperText('Contoh: Carbon, White Glossy.'),
                        TextInput::make('external_size_name')
                            ->label('Ukuran')
                            ->helperText('Contoh: MD, LG, XL, XXL.'),
                    ]),

                Section::make('Pemetaan Barang RSV (Internal)')
                    ->description('Pilih barang finish good RSV Helmet yang akan ditambah stoknya ketika unit varian ini diproduksi.')
                    ->columns(2)
                    ->schema([
                        InfiniteSelect::make('item_id')
                            ->label('Barang RSV')
                            ->helperText('Barang inventaris internal yang akan menerima penambahan stok.')
                            ->required()
                            ->getPaginatedOptionsUsing(function (int $offset, int $limit, ?string $search): array {
                                $query = Item::query()
                                    ->where('is_active', true)
                                    ->when(filled($search), fn (Builder $query): Builder => $query->where(
                                        fn (Builder $query): Builder => $query
                                            ->where('item_code', 'like', "%{$search}%")
                                            ->orWhere('item_name', 'like', "%{$search}%"),
                                    ));

                                $rows = $query->orderBy('item_name')->skip($offset)->take($limit + 1)->get();

                                return [
                                    'options' => $rows->take($limit)
                                        ->mapWithKeys(fn (Item $item): array => [$item->getKey() => "{$item->item_code} - {$item->item_name}"])
                                        ->all(),
                                    'hasMore' => $rows->count() > $limit,
                                ];
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                $item = Item::query()->find($value);

                                return $item ? "{$item->item_code} - {$item->item_name}" : null;
                            }),
                        Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->helperText('Jika nonaktif, unit varian ini akan dilewati saat sinkronisasi stok.')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('external_variant_code')
                    ->label('Kode Varian')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('external_variant_id')
                    ->label('ID Varian')
                    ->sortable(),
                TextColumn::make('external_product_name')
                    ->label('Produk')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('external_color_name')
                    ->label('Warna')
                    ->placeholder('-'),
                TextColumn::make('external_size_name')
                    ->label('Ukuran')
                    ->placeholder('-'),
                TextColumn::make('item.item_name')
                    ->label('Barang RSV Internal')
                    ->searchable()
                    ->description(fn (ExternalItemMapping $record): ?string => $record->item?->item_code)
                    ->wrap(),
                TextColumn::make('synced_barangs_count')
                    ->label('Unit Masuk')
                    ->counts('syncedBarangs')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExternalItemMappings::route('/'),
            'create' => CreateExternalItemMapping::route('/create'),
            'edit' => EditExternalItemMapping::route('/{record}/edit'),
        ];
    }
}
