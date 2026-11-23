<?php

namespace App\Filament\Resources\ProductionBatches\Pages;

use App\Filament\Exports\ProductionBatchItemExporter;
use App\Filament\Imports\ProductionBatchItemImporter;
use App\Filament\Resources\ProductionBatches\ProductionBatchResource;
use App\Models\ProductionBatch;
use App\Services\BatchRequirementService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class ViewProductionBatch extends ViewRecord
{
    protected static string $resource = ProductionBatchResource::class;

    protected static ?string $title = 'Detail Batch Produksi';

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi batch')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('batch_code')
                            ->label('Kode batch'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ProductionBatch::statusOptions()[$state] ?? $state)
                            ->color(fn (string $state): string => match ($state) {
                                ProductionBatch::STATUS_RUNNING => 'info',
                                ProductionBatch::STATUS_COMPLETED => 'success',
                                ProductionBatch::STATUS_CANCELLED => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('planned_quantity')
                            ->label('Rencana hasil')
                            ->state(fn (ProductionBatch $record): string => Number::format(
                                (float) $record->items->sum('quantity_planned'),
                                maxPrecision: 3,
                                locale: app()->getLocale(),
                            )),
                        TextEntry::make('materialWarehouse.name')
                            ->label('Gudang material')
                            ->placeholder('—'),
                        TextEntry::make('outputWarehouse.name')
                            ->label('Gudang hasil produksi')
                            ->placeholder('—'),
                        TextEntry::make('started_at')
                            ->label('Tanggal mulai')
                            ->date()
                            ->placeholder('—'),
                        TextEntry::make('finished_at')
                            ->label('Tanggal selesai')
                            ->date()
                            ->placeholder('—'),
                        TextEntry::make('notes')
                            ->label('Catatan')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Section::make('Realisasi produksi')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('actual_output')
                            ->label('Hasil produksi tercatat')
                            ->state(fn (ProductionBatch $record): string => Number::format(
                                (float) $record->actual_output,
                                maxPrecision: 3,
                                locale: app()->getLocale(),
                            )),
                        TextEntry::make('planned_materials')
                            ->label('Jenis material dibutuhkan')
                            ->state(fn (ProductionBatch $record): string => (string) $record->materials()->count()),
                        TextEntry::make('shortage_materials')
                            ->label('Material kurang')
                            ->badge()
                            ->state(fn (ProductionBatch $record): string => (string) $record->materials()
                                ->get()
                                ->filter(fn ($material): bool => $material->shortage_quantity > 0)
                                ->count())
                            ->color(fn (string $state): string => (int) $state > 0 ? 'danger' : 'success'),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('start')
                ->label('Mulai produksi')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn (ProductionBatch $record): bool => $record->status === ProductionBatch::STATUS_DRAFT)
                ->requiresConfirmation()
                ->modalHeading('Mulai produksi?')
                ->modalDescription('Batch akan ditandai sedang berjalan. Transaksi stok keluar/masuk bisa dicatat dengan memilih batch ini.')
                ->action(function (ProductionBatch $record): void {
                    $record->update([
                        'status' => ProductionBatch::STATUS_RUNNING,
                        'started_at' => $record->started_at ?? today(),
                    ]);

                    Notification::make()
                        ->title('Produksi dimulai')
                        ->success()
                        ->send();
                }),
            Action::make('finish')
                ->label('Selesaikan batch')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (ProductionBatch $record): bool => $record->status === ProductionBatch::STATUS_RUNNING)
                ->requiresConfirmation()
                ->modalHeading('Selesaikan batch?')
                ->modalDescription('Batch akan ditandai selesai dan tanggal selesai diisi hari ini.')
                ->action(function (ProductionBatch $record): void {
                    $record->update([
                        'status' => ProductionBatch::STATUS_COMPLETED,
                        'finished_at' => $record->finished_at ?? today(),
                    ]);

                    Notification::make()
                        ->title('Batch selesai')
                        ->success()
                        ->send();
                }),
            Action::make('recalculate')
                ->label('Hitung ulang kebutuhan')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (ProductionBatch $record): void {
                    app(BatchRequirementService::class)->recalculate($record);

                    Notification::make()
                        ->title('Kebutuhan material diperbarui')
                        ->success()
                        ->send();
                }),
            EditAction::make(),
            ImportAction::make('import_batch_items')
                ->importer(ProductionBatchItemImporter::class)
                ->label('Impor produk')
                ->options(fn (ProductionBatch $record): array => [
                    'production_batch_id' => $record->getKey(),
                ]),
            ExportAction::make('export_batch_items')
                ->exporter(ProductionBatchItemExporter::class)
                ->label('Ekspor produk')
                ->modifyQueryUsing(fn ($query, ProductionBatch $record) => $query->where(
                    'production_batch_id', $record->getKey(),
                )),
        ];
    }
}
