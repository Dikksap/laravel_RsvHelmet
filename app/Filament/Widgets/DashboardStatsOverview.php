<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Filament\Resources\ItemRequests\ItemRequestResource;
use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\ProductionBatches\ProductionBatchResource;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\ItemRequest;
use App\Models\ProductionBatch;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        return [
            Stat::make('Total barang', Item::query()->count())
                ->icon('heroicon-o-cube')
                ->color('primary')
                ->url(ItemResource::getUrl('index')),
            Stat::make('Transaksi hari ini', InventoryMovement::query()->whereDate('occurred_at', today())->count())
                ->description('Hari ini')
                ->icon('heroicon-o-arrows-right-left')
                ->color('success')
                ->url(InventoryMovementResource::getUrl('index')),
            Stat::make('Batch berjalan', ProductionBatch::query()->where('status', ProductionBatch::STATUS_RUNNING)->count())
                ->icon('heroicon-o-play-circle')
                ->color('info')
                ->url(ProductionBatchResource::getUrl('index')),
            Stat::make('Permintaan baru', ItemRequest::query()
                ->whereIn('status', [ItemRequest::STATUS_PENDING, ItemRequest::STATUS_PROCESSING])
                ->count())
                ->icon('heroicon-o-clipboard-document-list')
                ->color('warning')
                ->url(ItemRequestResource::getUrl('index')),
        ];
    }
}
