<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\ProductionBatch;
use Illuminate\Database\Eloquent\Builder;

class BatchProgressService
{
    /**
     * @return array{
     *     running_batches: int,
     *     planned_quantity: float,
     *     actual_quantity: float,
     *     progress_percentage: float,
     *     completed_this_month: int
     * }
     */
    public function summary(): array
    {
        $now = now();
        $runningBatches = $this->runningBatchQuery()->get();
        $plannedQuantity = (float) $runningBatches->sum(
            fn (ProductionBatch $batch): float => (float) $batch->planned_quantity,
        );
        $actualQuantity = (float) $runningBatches->sum(
            fn (ProductionBatch $batch): float => (float) $batch->actual_output,
        );

        return [
            'running_batches' => $runningBatches->count(),
            'planned_quantity' => $plannedQuantity,
            'actual_quantity' => $actualQuantity,
            'progress_percentage' => self::percentage($plannedQuantity, $actualQuantity),
            'completed_this_month' => ProductionBatch::query()
                ->where('status', ProductionBatch::STATUS_COMPLETED)
                ->whereBetween('finished_at', [
                    $now->copy()->startOfMonth()->toDateString(),
                    $now->copy()->endOfMonth()->toDateString(),
                ])
                ->count(),
        ];
    }

    /**
     * @return array<int, array{
     *     batch_code: string,
     *     planned_quantity: float,
     *     actual_quantity: float,
     *     progress_percentage: float
     * }>
     */
    public function runningBatches(int $limit = 8): array
    {
        return $this->runningBatchQuery()
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(max(0, min(8, $limit)))
            ->get()
            ->map(fn (ProductionBatch $batch): array => $this->formatBatchProgress($batch))
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = ProductionBatch::query()
            ->selectRaw('status, COUNT(*) AS status_count')
            ->groupBy('status')
            ->pluck('status_count', 'status');

        return collect(array_keys(ProductionBatch::statusOptions()))
            ->mapWithKeys(fn (string $status): array => [$status => (int) ($counts->get($status) ?? 0)])
            ->all();
    }

    /**
     * @return array<int, array{
     *     batch_code: string,
     *     finished_at: ?string,
     *     planned_quantity: float,
     *     actual_quantity: float,
     *     progress_percentage: float
     * }>
     */
    public function recentCompleted(int $limit = 8): array
    {
        return ProductionBatch::query()
            ->withSum('items as planned_quantity', 'quantity_planned')
            ->withSum([
                'movements as actual_output' => fn (Builder $query): Builder => $query
                    ->where('type', InventoryMovement::TYPE_IN_PRODUCTION),
            ], 'quantity')
            ->where('status', ProductionBatch::STATUS_COMPLETED)
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->limit(max(0, $limit))
            ->get()
            ->map(fn (ProductionBatch $batch): array => [
                'batch_code' => $batch->batch_code,
                'finished_at' => $batch->finished_at?->toDateString(),
                ...$this->formatBatchProgress($batch),
            ])
            ->all();
    }

    public static function percentage(float $plannedQuantity, float $actualQuantity): float
    {
        if ($plannedQuantity <= 0) {
            return 0.0;
        }

        return round($actualQuantity / $plannedQuantity * 100, 1);
    }

    /**
     * @return Builder<ProductionBatch>
     */
    private function runningBatchQuery(): Builder
    {
        return ProductionBatch::query()
            ->withSum('items as planned_quantity', 'quantity_planned')
            ->withSum([
                'movements as actual_output' => fn (Builder $query): Builder => $query
                    ->where('type', InventoryMovement::TYPE_IN_PRODUCTION),
            ], 'quantity')
            ->where('status', ProductionBatch::STATUS_RUNNING);
    }

    /**
     * @return array{
     *     planned_quantity: float,
     *     actual_quantity: float,
     *     progress_percentage: float
     * }
     */
    private function formatBatchProgress(ProductionBatch $batch): array
    {
        $plannedQuantity = (float) $batch->planned_quantity;
        $actualQuantity = (float) $batch->actual_output;

        return [
            'planned_quantity' => $plannedQuantity,
            'actual_quantity' => $actualQuantity,
            'progress_percentage' => self::percentage($plannedQuantity, $actualQuantity),
        ];
    }
}
