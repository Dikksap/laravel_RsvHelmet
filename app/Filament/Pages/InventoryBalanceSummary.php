<?php

namespace App\Filament\Pages;

use App\Models\Item;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use UnitEnum;

class InventoryBalanceSummary extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = 'Gudang';

    protected static ?string $navigationLabel = 'Total Stok Gudang';

    protected static ?string $title = 'Total Stok Seluruh Gudang';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.inventory-balance-summary';

    public function getSubheading(): ?string
    {
        return 'Ringkasan jumlah stok per barang. Pilih filter gudang untuk melihat total pada gudang tertentu.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->getStockSummaryQuery())
            ->columns([
                TextColumn::make('item_code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('item_name')
                    ->label('Barang')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('type.name')
                    ->label('Jenis')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('brand.name')
                    ->label('Merek')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_stock_all_warehouses')
                    ->label('Total stok')
                    ->numeric(maxDecimalPlaces: 3)
                    ->default(0)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->options(fn (): array => Warehouse::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->multiple()
                    ->searchable()
                    ->query(fn (Builder $query): Builder => $this->whereStoredInWarehouses($query)),
                SelectFilter::make('item_type_id')
                    ->label('Jenis barang')
                    ->relationship('type', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('brand_id')
                    ->label('Merek')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('stock_status')
                    ->label('Status stok')
                    ->options([
                        'in_stock' => 'Ada stok',
                        'out_of_stock' => 'Kosong',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'in_stock' => $this->whereStocked($query),
                        'out_of_stock' => $this->whereStocked($query, isStocked: false),
                        default => $query,
                    }),
            ]);
    }

    protected function getStockSummaryQuery(): Builder
    {
        $warehouseIds = $this->getSelectedWarehouseIds();

        return Item::query()
            ->where('is_stockable', true)
            ->withSum(
                ['inventoryBalances as total_stock_all_warehouses' => fn (Builder $query): Builder => $query->when(
                    $warehouseIds !== [],
                    fn (Builder $query): Builder => $query->whereIn('warehouse_id', $warehouseIds),
                )],
                'quantity_on_hand',
            );
    }

    /**
     * The warehouse filter also scopes the stock total, so the summary reflects
     * only the selected warehouses.
     *
     * @return array<int, string>
     */
    protected function getSelectedWarehouseIds(): array
    {
        $state = $this->tableFilters['warehouse_id'] ?? [];

        return array_values(array_filter([
            ...Arr::wrap($state['value'] ?? null),
            ...Arr::wrap($state['values'] ?? []),
        ], fn (mixed $value): bool => filled($value)));
    }

    protected function whereStoredInWarehouses(Builder $query): Builder
    {
        $warehouseIds = $this->getSelectedWarehouseIds();

        return $warehouseIds === []
            ? $query
            : $query->whereHas(
                'inventoryBalances',
                fn (Builder $query): Builder => $query->whereIn('warehouse_id', $warehouseIds),
            );
    }

    protected function whereStocked(Builder $query, bool $isStocked = true): Builder
    {
        $warehouseIds = $this->getSelectedWarehouseIds();

        $callback = fn (Builder $query): Builder => $query
            ->where('quantity_on_hand', '>', 0)
            ->when(
                $warehouseIds !== [],
                fn (Builder $query): Builder => $query->whereIn('warehouse_id', $warehouseIds),
            );

        return $isStocked
            ? $query->whereHas('inventoryBalances', $callback)
            : $query->whereDoesntHave('inventoryBalances', $callback);
    }
}
