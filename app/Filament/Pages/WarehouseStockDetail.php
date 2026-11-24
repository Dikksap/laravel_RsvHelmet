<?php

namespace App\Filament\Pages;

use App\Filament\Resources\InventoryBalances\InventoryBalanceResource;
use App\Models\InventoryBalance;
use App\Models\Warehouse;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class WarehouseStockDetail extends Page implements HasTable
{
    use InteractsWithTable;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|UnitEnum|null $navigationGroup = 'Gudang';

    protected static ?string $slug = 'stok-per-gudang/{warehouse}';

    protected string $view = 'filament.pages.warehouse-stock-detail';

    public Warehouse $warehouse;

    public function mount(Warehouse $warehouse): void
    {
        $this->warehouse = $warehouse;
    }

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'stok-per-gudang.detail';
    }

    public function getTitle(): string|Htmlable
    {
        return "Stok {$this->warehouse->name}";
    }

    public function getSubheading(): ?string
    {
        return 'Daftar barang beserta jumlah stok yang tersimpan pada gudang ini.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Tambah stok')
                ->icon('heroicon-o-plus')
                ->url(fn (): string => InventoryBalanceResource::getUrl('create', [
                    'warehouse_id' => $this->warehouse->getKey(),
                ])),
            Action::make('back')
                ->label('Pilih gudang lain')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(WarehouseStock::getUrl()),
        ];
    }

    /**
     * @return array{total_items: int, total_quantity: float, low_stock_count: int, out_of_stock_count: int}
     */
    public function getSummary(): array
    {
        $summary = InventoryBalance::query()
            ->where('warehouse_id', $this->warehouse->getKey())
            ->selectRaw('COUNT(*) as total_items')
            ->selectRaw('COALESCE(SUM(quantity_on_hand), 0) as total_quantity')
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity_on_hand > 0 AND quantity_on_hand <= minimum_quantity THEN 1 ELSE 0 END), 0) as low_stock_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity_on_hand <= 0 THEN 1 ELSE 0 END), 0) as out_of_stock_count')
            ->first();

        return [
            'total_items' => (int) ($summary->total_items ?? 0),
            'total_quantity' => (float) ($summary->total_quantity ?? 0),
            'low_stock_count' => (int) ($summary->low_stock_count ?? 0),
            'out_of_stock_count' => (int) ($summary->out_of_stock_count ?? 0),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InventoryBalance::query()
                    ->where('warehouse_id', $this->warehouse->getKey())
                    ->with(['item.category'])
            )
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
                TextColumn::make('item.category.name')
                    ->label('Kategori')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('quantity_on_hand')
                    ->label('Stok')
                    ->numeric(maxDecimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('minimum_quantity')
                    ->label('Stok minimum')
                    ->numeric(maxDecimalPlaces: 3)
                    ->sortable(),
                InventoryBalanceResource::stockStatusColumn(),
                TextColumn::make('last_counted_at')
                    ->label('Terakhir dihitung')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                InventoryBalanceResource::stockStatusFilter(),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Ubah')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (InventoryBalance $record): string => InventoryBalanceResource::getUrl('edit', [
                        'record' => $record,
                    ])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    InventoryBalanceResource::transferBulkAction(),
                ]),
            ])
            ->defaultSort('item.item_name')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Belum ada stok di gudang ini')
            ->emptyStateDescription('Tambahkan stok barang lewat tombol Tambah stok atau catat transaksi stok masuk.');
    }
}
