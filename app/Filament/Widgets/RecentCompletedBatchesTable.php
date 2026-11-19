<?php

namespace App\Filament\Widgets;

use App\Models\InventoryMovement;
use App\Models\ProductionBatch;
use App\Services\BatchProgressService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class RecentCompletedBatchesTable extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Batch selesai terbaru')
            ->query(
                ProductionBatch::query()
                    ->withSum('items as planned_quantity', 'quantity_planned')
                    ->withSum([
                        'movements as actual_output' => fn (Builder $query): Builder => $query
                            ->where('type', InventoryMovement::TYPE_IN_PRODUCTION),
                    ], 'quantity')
                    ->where('status', ProductionBatch::STATUS_COMPLETED)
                    ->orderByDesc('finished_at')
                    ->orderByDesc('id')
                    ->limit(8)
            )
            ->columns([
                TextColumn::make('batch_code')
                    ->label('Kode batch')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('finished_at')
                    ->label('Selesai')
                    ->date()
                    ->sortable(),
                TextColumn::make('planned_quantity')
                    ->label('Rencana hasil')
                    ->numeric(maxDecimalPlaces: 3),
                TextColumn::make('actual_output')
                    ->label('Realisasi hasil')
                    ->numeric(maxDecimalPlaces: 3),
                TextColumn::make('progress_percentage')
                    ->label('Progres')
                    ->state(fn (ProductionBatch $record): float => BatchProgressService::percentage(
                        (float) $record->planned_quantity,
                        (float) $record->actual_output,
                    ))
                    ->formatStateUsing(fn (float $state): string => Number::format(
                        $state,
                        maxPrecision: 1,
                        locale: app()->getLocale(),
                    ).'%'),
            ])
            ->defaultSort('finished_at', 'desc')
            ->emptyStateHeading('Belum ada batch selesai')
            ->emptyStateDescription('Batch yang telah diselesaikan akan muncul di sini.');
    }
}
