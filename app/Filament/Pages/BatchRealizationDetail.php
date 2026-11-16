<?php

namespace App\Filament\Pages;

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\ProductionBatch;
use App\Models\ProductionBatchItem;
use App\Models\ProductionBatchMaterial;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BatchRealizationDetail extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'batch-realization/{batch}';

    protected static ?string $title = 'Detail Realisasi Produksi';

    protected string $view = 'filament.pages.batch-realization-detail';

    public ProductionBatch $batch;

    public function mount(ProductionBatch $batch): void
    {
        $this->batch = $batch->load([
            'items.item',
            'materials.item',
            'movements.item',
            'movements.warehouse',
            'materialWarehouse',
            'outputWarehouse',
            'creator',
        ]);
    }

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'batch-realization.detail';
    }

    public function getTitle(): string|Htmlable
    {
        return "Realisasi {$this->batch->batch_code}";
    }

    public function getSubheading(): ?string
    {
        return 'Rincian rencana dan realisasi hasil produksi serta pemakaian material batch ini.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Unduh CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
            Action::make('print')
                ->label('Cetak')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(fn () => $this->js('window.print()')),
            Action::make('back')
                ->label('Kembali ke laporan')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(BatchRealization::getUrl()),
        ];
    }

    /**
     * Rencana vs realisasi hasil produksi per barang.
     *
     * @return array<int, array{item: mixed, planned: float, actual: float, difference: float, percentage: float}>
     */
    public function outputRealisations(): array
    {
        $actualByItem = $this->batch->movements
            ->where('type', InventoryMovement::TYPE_IN_PRODUCTION)
            ->groupBy('item_id')
            ->map(fn (Collection $movements): float => (float) $movements->sum('quantity'));

        return $this->batch->items
            ->map(function (ProductionBatchItem $item) use ($actualByItem): array {
                $planned = (float) $item->quantity_planned;
                $actual = (float) ($actualByItem->get($item->item_id) ?? 0);

                return [
                    'item' => $item->item,
                    'planned' => $planned,
                    'actual' => $actual,
                    'difference' => $actual - $planned,
                    'percentage' => $this->percentage($planned, $actual),
                ];
            })
            ->all();
    }

    /**
     * Kebutuhan vs pemakaian material per barang.
     *
     * @return array<int, array{item: mixed, required: float, used: float, remaining: float, available: float, shortage: float}>
     */
    public function materialRealisations(): array
    {
        $usedByItem = $this->batch->movements
            ->where('type', InventoryMovement::TYPE_OUT_PRODUCTION)
            ->groupBy('item_id')
            ->map(fn (Collection $movements): float => (float) $movements->sum('quantity'));

        $availableByItem = InventoryBalance::query()
            ->whereIn('item_id', $this->batch->materials->pluck('item_id')->unique()->all())
            ->when(
                filled($this->batch->material_warehouse_id),
                fn (Builder $query): Builder => $query->where('warehouse_id', $this->batch->material_warehouse_id),
            )
            ->get()
            ->groupBy('item_id')
            ->map(fn (Collection $balances): float => (float) $balances
                ->sum(fn (InventoryBalance $balance): float => (float) $balance->quantity_on_hand));

        return $this->batch->materials
            ->map(function (ProductionBatchMaterial $material) use ($usedByItem, $availableByItem): array {
                $required = (float) $material->quantity_required;
                $used = (float) ($usedByItem->get($material->item_id) ?? 0);
                $available = (float) ($availableByItem->get($material->item_id) ?? 0);

                return [
                    'item' => $material->item,
                    'required' => $required,
                    'used' => $used,
                    'remaining' => max($required - $used, 0.0),
                    'available' => $available,
                    'shortage' => max($required - $available, 0.0),
                ];
            })
            ->all();
    }

    public function percentage(float $planned, float $actual): float
    {
        if ($planned <= 0) {
            return 0.0;
        }

        return round($actual / $planned * 100, 1);
    }

    public function exportCsv(): StreamedResponse
    {
        $outputs = $this->outputRealisations();
        $materials = $this->materialRealisations();

        $fileName = 'realisasi-'.str($this->batch->batch_code)->slug().'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($outputs, $materials): void {
            $handle = fopen('php://output', 'w');

            // BOM agar karakter Indonesia tampil benar di Excel.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Realisasi Produksi']);
            fputcsv($handle, ['Kode batch', $this->batch->batch_code]);
            fputcsv($handle, ['Status', $this->batch->status_label]);
            fputcsv($handle, ['Gudang material', $this->batch->materialWarehouse?->name ?? '-']);
            fputcsv($handle, ['Gudang hasil produksi', $this->batch->outputWarehouse?->name ?? '-']);
            fputcsv($handle, ['Tanggal mulai', $this->batch->started_at?->format('Y-m-d') ?? '-']);
            fputcsv($handle, ['Tanggal selesai', $this->batch->finished_at?->format('Y-m-d') ?? '-']);
            fputcsv($handle, ['Catatan', $this->batch->notes ?? '-']);
            fputcsv($handle, []);

            fputcsv($handle, ['Hasil Produksi']);
            fputcsv($handle, ['Kode', 'Barang', 'Rencana', 'Realisasi', 'Selisih', 'Tercapai (%)']);

            foreach ($outputs as $row) {
                fputcsv($handle, [
                    $row['item']?->item_code ?? '-',
                    $row['item']?->item_name ?? '-',
                    $row['planned'],
                    $row['actual'],
                    $row['difference'],
                    $row['percentage'],
                ]);
            }

            fputcsv($handle, []);

            fputcsv($handle, ['Realisasi Material']);
            fputcsv($handle, ['Kode', 'Material', 'Kebutuhan', 'Terpakai', 'Sisa kebutuhan', 'Kekurangan stok']);

            foreach ($materials as $row) {
                fputcsv($handle, [
                    $row['item']?->item_code ?? '-',
                    $row['item']?->item_name ?? '-',
                    $row['required'],
                    $row['used'],
                    $row['remaining'],
                    $row['shortage'],
                ]);
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }
}
