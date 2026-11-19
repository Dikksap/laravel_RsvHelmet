<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\BatchRealizationDetail;
use App\Filament\Resources\ProductionBatches\ProductionBatchResource;
use App\Models\InventoryMovement;
use App\Models\ProductionBatch;
use App\Services\BatchProgressService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class BatchProgressTable extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Progres batch produksi')
            ->query(
                ProductionBatch::query()
                    ->withSum('items as planned_quantity', 'quantity_planned')
                    ->withSum([
                        'movements as actual_output' => fn (Builder $query): Builder => $query
                            ->where('type', InventoryMovement::TYPE_IN_PRODUCTION),
                    ], 'quantity')
            )
            ->columns([
                TextColumn::make('batch_code')
                    ->label('Kode batch')
                    ->searchable()
                    ->sortable(),
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
                TextColumn::make('started_at')
                    ->label('Mulai')
                    ->date()
                    ->sortable(),
                TextColumn::make('planned_quantity')
                    ->label('Rencana hasil')
                    ->numeric(maxDecimalPlaces: 3),
                TextColumn::make('actual_output')
                    ->label('Realisasi hasil')
                    ->numeric(maxDecimalPlaces: 3),
                TextColumn::make('difference')
                    ->label('Selisih')
                    ->state(fn (ProductionBatch $record): float => (float) $record->actual_output - (float) $record->planned_quantity)
                    ->numeric(maxDecimalPlaces: 3),
                ViewColumn::make('progress_percentage')
                    ->label('Progres')
                    ->state(fn (ProductionBatch $record): float => BatchProgressService::percentage(
                        (float) $record->planned_quantity,
                        (float) $record->actual_output,
                    ))
                    ->view('filament.tables.columns.progress-bar'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options(ProductionBatch::statusOptions())
                    ->default([
                        ProductionBatch::STATUS_DRAFT,
                        ProductionBatch::STATUS_RUNNING,
                    ]),
            ])
            ->recordActions([
                Action::make('detail')
                    ->label('Detail realisasi')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->url(fn (ProductionBatch $record): string => BatchRealizationDetail::getUrl(['batch' => $record->getKey()])),
                Action::make('view')
                    ->label('Lihat batch')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (ProductionBatch $record): string => ProductionBatchResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('started_at', 'desc')
            ->poll('60s')
            ->emptyStateHeading('Belum ada batch berjalan atau direncanakan')
            ->emptyStateDescription('Batch baru akan muncul di tabel ini setelah dibuat.');
    }
}
