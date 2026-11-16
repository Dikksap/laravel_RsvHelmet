<?php

namespace App\Services;

use App\Models\ProductionBatch;
use Illuminate\Support\Facades\DB;

class BatchRequirementService
{
    /**
     * Hitung ulang kebutuhan material batch dari komposisi (BOM) tiap barang hasil.
     * Hasilnya disimpan sebagai snapshot di production_batch_materials.
     */
    public function recalculate(ProductionBatch $batch): void
    {
        $batch->load(['items.item.components']);

        $requirements = [];

        foreach ($batch->items as $batchItem) {
            $item = $batchItem->item;

            if (! $item) {
                continue;
            }

            foreach ($item->components->where('is_active', true) as $component) {
                $itemId = $component->component_item_id;
                $quantity = (float) $component->quantity * (float) $batchItem->quantity_planned;

                $requirements[$itemId] = ($requirements[$itemId] ?? 0.0) + $quantity;
            }
        }

        DB::transaction(function () use ($batch, $requirements): void {
            $existing = $batch->materials()->pluck('id', 'item_id');

            foreach ($requirements as $itemId => $quantity) {
                $existingId = $existing->get($itemId);

                if ($existingId) {
                    $batch->materials()->whereKey($existingId)->update([
                        'quantity_required' => $quantity,
                    ]);

                    continue;
                }

                $batch->materials()->create([
                    'item_id' => $itemId,
                    'quantity_required' => $quantity,
                ]);
            }

            $batch->materials()
                ->whereNotIn('item_id', array_keys($requirements))
                ->delete();
        });
    }
}
