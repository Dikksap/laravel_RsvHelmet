<?php
en

namespace App\Filament\Pages;

use App\Filament\Resources\ProductionBatches\ProductionBatchResource;
use App\Models\InventoryMovement;
use App\Models\ProductionBatch;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class BatchRealization extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = 'Produksi';

    protected static ?string $navigationLabel = 'Laporan Realisasi';

    protected static ?string $title = 'Laporan Realisasi Produksi';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.batch-realization';

    public function getSubheading(): ?string
    {
        return 'Ringkasan rencana vs realisasi hasil produksi tiap batch, termasuk jumlah material yang masih kurang.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ProductionBatch::query()
                    ->withSum('items as planned_quantity', 'quantity_planned')
                    ->withSum(['movements as actual_output' => fn (Builder $query): Builder => $query
                        ->where('type', InventoryMovement::TYPE_IN_PRODUCTION)], 'quantity')
                    ->withCount('materials')
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
                TextColumn::make('planned_quantity')
                    ->label('Rencana hasil')
                    ->numeric(maxDecimalPlaces: 3),
                TextColumn::make('actual_output')
                    ->label('Realisasi hasil')
                    ->numeric(maxDecimalPlaces: 3),
                TextColumn::make('materials_count')
                    ->label('Jenis material'),
                TextColumn::make('shortage')
                    ->label('Material kurang')
                    ->badge()
                    ->state(fn (ProductionBatch $record): string => (string) $record->materials()
                        ->get()
                        ->filter(fn ($material): bool => $material->shortage_quantity > 0)
                        ->count())
                    ->color(fn (string $state): string => (int) $state > 0 ? 'danger' : 'success'),
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
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Belum ada batch produksi')
            ->emptyStateDescription('Buat batch lewat menu Batch Produksi untuk melihat laporan realisasinya.');
    }
}
