<?php

namespace App\Filament\Pages;

use App\Filament\Resources\InventoryBalances\InventoryBalanceResource;
use App\Models\InventoryBalance;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class WarehouseStock extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Gudang';

    protected static ?string $navigationLabel = 'Stok per Gudang';

    protected static ?string $title = 'Stok per Gudang';

    protected static ?string $slug = 'stok-per-gudang';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.warehouse-stock';

    public static function getNavigationBadge(): ?string
    {
        $count = InventoryBalance::query()->lowStock()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Jumlah baris stok yang sudah mencapai atau di bawah stok minimum.';
    }

    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [static::getRouteName(), WarehouseStockDetail::getRouteName()];
    }

    public function getSubheading(): ?string
    {
        return 'Pilih gudang untuk melihat daftar stok yang tersimpan di dalamnya.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('all_stock')
                ->label('Semua stok')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->tooltip('Lihat seluruh stok dari semua gudang dalam satu tabel.')
                ->url(InventoryBalanceResource::getUrl()),
        ];
    }

    /**
     * @return Collection<int, Warehouse>
     */
    public function getWarehouses(): Collection
    {
        return Warehouse::query()
            ->withCount([
                'balances as total_items',
                'balances as low_stock_count' => fn (Builder $query): Builder => $query
                    ->where('quantity_on_hand', '>', 0)
                    ->whereColumn('quantity_on_hand', '<=', 'minimum_quantity'),
                'balances as out_of_stock_count' => fn (Builder $query): Builder => $query
                    ->where('quantity_on_hand', '<=', 0),
            ])
            ->withSum('balances as total_quantity', 'quantity_on_hand')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }
}
