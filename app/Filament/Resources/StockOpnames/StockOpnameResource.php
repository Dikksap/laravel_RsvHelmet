<?php

namespace App\Filament\Resources\StockOpnames;

use App\Filament\Resources\StockOpnames\Pages\CreateStockOpname;
use App\Filament\Resources\StockOpnames\Pages\ListStockOpnames;
use App\Filament\Resources\StockOpnames\Pages\ViewStockOpname;
use App\Filament\Resources\StockOpnames\RelationManagers\LinesRelationManager;
use App\Filament\Resources\StockOpnames\RelationManagers\MovementsRelationManager;
use App\Models\StockOpname;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class StockOpnameResource extends Resource
{
    protected static ?string $model = StockOpname::class;

    protected static string|UnitEnum|null $navigationGroup = 'Gudang';

    protected static ?string $navigationLabel = 'Stok Opname';

    protected static ?string $modelLabel = 'Stok Opname';

    protected static ?string $pluralModelLabel = 'Stok Opname';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 15;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getNavigationBadge(): ?string
    {
        $count = StockOpname::query()
            ->where('status', StockOpname::STATUS_DRAFT)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Jumlah stok opname draft yang belum diposting.';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi opname')
                    ->columns(2)
                    ->schema([
                        Select::make('warehouse_id')
                            ->label('Gudang')
                            ->relationship(
                                name: 'warehouse',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('is_active', true),
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Semua barang yang bisa distok akan dimuat sebagai baris hitung fisik.'),
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withCount('lines')
                ->withCount([
                    'lines as counted_lines_count' => fn (Builder $query): Builder => $query
                        ->whereNotNull('counted_quantity'),
                ])
                ->withCount([
                    'lines as difference_lines_count' => fn (Builder $query): Builder => $query
                        ->whereNotNull('counted_quantity')
                        ->whereColumn('counted_quantity', '!=', 'system_quantity'),
                ]))
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => StockOpname::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => self::statusColor($state)),
                TextColumn::make('counted_lines_count')
                    ->label('Progres hitung')
                    ->state(fn (StockOpname $record): string => ((int) $record->counted_lines_count)
                        .' / '.((int) $record->lines_count))
                    ->badge()
                    ->color('info'),
                TextColumn::make('difference_lines_count')
                    ->label('Berselisih')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'success'),
                TextColumn::make('creator.name')
                    ->label('Dibuat oleh')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('completed_at')
                    ->label('Diselesaikan')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StockOpname::statusOptions()),
                SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->relationship('warehouse', 'name'),
                Filter::make('created_at')
                    ->label('Tanggal dibuat')
                    ->form([
                        DatePicker::make('from')
                            ->label('Dari tanggal'),
                        DatePicker::make('until')
                            ->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['from'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date),
                        )
                        ->when(
                            $data['until'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date),
                        )),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make()
                    ->visible(fn (StockOpname $record): bool => $record->isDraft())
                    ->modalDescription('Dokumen draft beserta seluruh baris hitung fisiknya akan dihapus.'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Belum ada stok opname')
            ->emptyStateDescription('Buat stok opname untuk mulai menghitung stok fisik per gudang.');
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            StockOpname::STATUS_COMPLETED => 'success',
            StockOpname::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
    }

    public static function getRelations(): array
    {
        return [
            LinesRelationManager::class,
            MovementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockOpnames::route('/'),
            'create' => CreateStockOpname::route('/create'),
            'view' => ViewStockOpname::route('/{record}'),
        ];
    }
}
