<?php

namespace App\Filament\Pages;

use App\Models\Item;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('inventoryBalances')
                ->label('Rincian per gudang')
                ->icon('heroicon-o-building-storefront')
                ->color('gray')
                ->url(WarehouseStock::getUrl()),
        ];
    }

    /**
     * Ringkasan stok mengikuti filter, pencarian, dan urutan tabel yang aktif.
     *
     * @return array{items: int, stocked: int, out_of_stock: int, total_units: float}
     */
    public function getStockStats(): array
    {
        $query = $this->getFilteredTableQuery();

        $empty = [
            'items' => 0,
            'stocked' => 0,
            'out_of_stock' => 0,
            'total_units' => 0.0,
        ];

        if ($query === null) {
            return $empty;
        }

        $summary = DB::query()
            ->fromSub($query->toBase(), 'stock_summary')
            ->selectRaw('count(*) as items')
            ->selectRaw('coalesce(sum(case when total_stock_all_warehouses > 0 then 1 else 0 end), 0) as stocked')
            ->selectRaw('coalesce(sum(total_stock_all_warehouses), 0) as total_units')
            ->first();

        if ($summary === null) {
            return $empty;
        }

        $items = (int) $summary->items;
        $stocked = (int) $summary->stocked;

        return [
            'items' => $items,
            'stocked' => $stocked,
            'out_of_stock' => $items - $stocked,
            'total_units' => (float) $summary->total_units,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->getStockSummaryQuery())
            ->columns([
                TextColumn::make('item_code')
                    ->label('Kode')
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->copyMessage('Kode barang disalin')
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
                TextColumn::make('stock_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Item $record): string => (float) ($record->total_stock_all_warehouses ?? 0) > 0
                        ? 'in_stock'
                        : 'out_of_stock')
                    ->formatStateUsing(fn (string $state): string => $state === 'in_stock' ? 'Tersedia' : 'Kosong')
                    ->color(fn (string $state): string => $state === 'in_stock' ? 'success' : 'danger'),
                TextColumn::make('total_stock_all_warehouses')
                    ->label('Total stok')
                    ->numeric(maxDecimalPlaces: 3)
                    ->default(0)
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn (Item $record): ?string => (float) ($record->total_stock_all_warehouses ?? 0) > 0 ? null : 'danger')
                    ->suffix(fn (Item $record): ?string => $record->baseUom?->code)
                    ->tooltip('Akumulasi stok dari seluruh gudang yang dipilih.')
                    ->summarize(Sum::make()->label('Total')->numeric(maxDecimalPlaces: 3)),
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
            ->with('baseUom')
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
