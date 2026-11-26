<?php

namespace App\Filament\Resources\ProductionBatches;

use App\Filament\Resources\ProductionBatches\Pages\CreateProductionBatch;
use App\Filament\Resources\ProductionBatches\Pages\EditProductionBatch;
use App\Filament\Resources\ProductionBatches\Pages\ListProductionBatches;
use App\Filament\Resources\ProductionBatches\Pages\ViewProductionBatch;
use App\Filament\Resources\ProductionBatches\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\ProductionBatches\RelationManagers\MaterialsRelationManager;
use App\Filament\Resources\ProductionBatches\RelationManagers\MovementsRelationManager;
use App\Filament\Resources\ProductionBatches\RelationManagers\RawMaterialsRelationManager;
use App\Models\ProductionBatch;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ProductionBatchResource extends Resource
{
    protected static ?string $model = ProductionBatch::class;

    protected static string|UnitEnum|null $navigationGroup = 'Produksi';

    protected static ?string $navigationLabel = 'Batch Produksi';

    protected static ?string $modelLabel = 'Batch Produksi';

    protected static ?string $pluralModelLabel = 'Batch Produksi';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube-transparent';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'batch_code';

    public static function getNavigationBadge(): ?string
    {
        $count = ProductionBatch::query()
            ->where('status', ProductionBatch::STATUS_RUNNING)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'info';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Jumlah batch produksi yang sedang berjalan.';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi batch')
                    ->columns(2)
                    ->schema([
                        TextInput::make('batch_code')
                            ->label('Kode batch')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->default(fn (): string => 'BATCH-'.now()->format('Ymd-His')),
                        Select::make('status')
                            ->label('Status')
                            ->options(ProductionBatch::statusOptions())
                            ->default(ProductionBatch::STATUS_DRAFT)
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Status berubah lewat tombol Mulai Produksi / Selesaikan di halaman batch.'),
                        Select::make('material_warehouse_id')
                            ->label('Gudang material')
                            ->relationship('materialWarehouse', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Gudang asal material yang dipakai produksi.'),
                        Select::make('output_warehouse_id')
                            ->label('Gudang hasil produksi')
                            ->relationship('outputWarehouse', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Gudang tujuan barang jadi / semi material.'),
                        DatePicker::make('started_at')
                            ->label('Tanggal mulai'),
                        DatePicker::make('finished_at')
                            ->label('Tanggal selesai'),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withSum('items as planned_quantity', 'quantity_planned'))
            ->columns([
                TextColumn::make('batch_code')
                    ->label('Kode batch')
                    ->searchable()
                    ->sortable()
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ProductionBatch::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        ProductionBatch::STATUS_RUNNING => 'info',
                        ProductionBatch::STATUS_COMPLETED => 'success',
                        ProductionBatch::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('planned_quantity')
                    ->label('Rencana hasil')
                    ->numeric(maxDecimalPlaces: 3)
                    ->alignEnd(),
                TextColumn::make('materialWarehouse.name')
                    ->label('Gudang material')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('outputWarehouse.name')
                    ->label('Gudang hasil')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('started_at')
                    ->label('Mulai')
                    ->date()
                    ->sortable(),
                TextColumn::make('finished_at')
                    ->label('Selesai')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(ProductionBatch::statusOptions()),
                SelectFilter::make('material_warehouse_id')
                    ->label('Gudang material')
                    ->relationship('materialWarehouse', 'name'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton(),
                EditAction::make()
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->infinite(perPage: 50);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
            MaterialsRelationManager::class,
            RawMaterialsRelationManager::class,
            MovementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductionBatches::route('/'),
            'create' => CreateProductionBatch::route('/create'),
            'view' => ViewProductionBatch::route('/{record}'),
            'edit' => EditProductionBatch::route('/{record}/edit'),
        ];
    }
}
