<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\ItemComponent;
use App\Models\ProductionBatch;
use Illuminate\Support\Collection;

class BomExplosionService
{
    /**
     * Urai komposisi bertingkat batch sampai bahan baku mentah, yaitu item yang
     * tidak punya komponen aktif lagi, lalu jumlahkan kebutuhan tiap item.
     *
     * Hanya untuk informasi. Pemotongan stok produksi tetap memakai material
     * langsung pada production_batch_materials.
     *
     * @return Collection<int, array{item: Item, quantity_required: float, stock_on_hand: float, shortage_quantity: float}>
     */
    public function rawMaterials(ProductionBatch $batch): Collection
    {
        $batch->loadMissing(['items.item', 'materialWarehouse']);

        /** @var Collection<string, Collection<int, ItemComponent>> $componentsByParent */
        $componentsByParent = ItemComponent::query()
            ->where('is_active', true)
            ->with('componentItem')
            ->get()
            ->groupBy('parent_item_id');

        /** @var array<string, float> $requirements */
        $requirements = [];

        foreach ($batch->items as $batchItem) {
            $item = $batchItem->item;
            $plannedQuantity = (float) $batchItem->quantity_planned;

            if (! $item || $plannedQuantity <= 0) {
                continue;
            }

            $path = [$item->getKey() => true];

            foreach ($this->componentsOf($item, $componentsByParent) as $component) {
                $componentItem = $component->componentItem;

                if (! $componentItem) {
                    continue;
                }

                $this->explodeChild(
                    $componentItem,
                    $plannedQuantity * (float) $component->quantity,
                    $componentsByParent,
                    $requirements,
                    $path,
                );
            }
        }

        if ($requirements === []) {
            return collect();
        }

        $itemIds = array_keys($requirements);

        $stockByItem = InventoryBalance::query()
            ->whereIn('item_id', $itemIds)
            ->when(
                filled($batch->material_warehouse_id),
                fn ($query) => $query->where('warehouse_id', $batch->material_warehouse_id),
            )
            ->get()
            ->groupBy('item_id')
            ->map(fn (Collection $balances): float => (float) $balances->sum(
                fn (InventoryBalance $balance): float => (float) $balance->quantity_on_hand,
            ));

        $items = Item::query()
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy(fn (Item $item): string => $item->getKey());

        return collect($requirements)
            ->map(function (float $quantity, string $itemId) use ($items, $stockByItem): ?array {
                $item = $items->get($itemId);

                if (! $item) {
                    return null;
                }

                $required = round($quantity, 3);
                $onHand = (float) ($stockByItem->get($itemId) ?? 0.0);

                return [
                    'item' => $item,
                    'quantity_required' => $required,
                    'stock_on_hand' => $onHand,
                    'shortage_quantity' => max(round($required - $onHand, 3), 0.0),
                ];
            })
            ->filter()
            ->sortBy(fn (array $row): string => $row['item']->item_code)
            ->values();
    }

    /**
     * @param  Collection<string, Collection<int, ItemComponent>>  $componentsByParent
     * @param  array<string, float>  $requirements
     * @param  array<string, true>  $path
     */
    private function explodeChild(
        Item $item,
        float $quantity,
        Collection $componentsByParent,
        array &$requirements,
        array $path,
    ): void {
        $components = $this->componentsOf($item, $componentsByParent);

        if ($components->isEmpty() || isset($path[$item->getKey()])) {
            $requirements[$item->getKey()] = ($requirements[$item->getKey()] ?? 0.0) + $quantity;

            return;
        }

        $path[$item->getKey()] = true;

        foreach ($components as $component) {
            if (! $component->componentItem) {
                continue;
            }

            $this->explodeChild(
                $component->componentItem,
                $quantity * (float) $component->quantity,
                $componentsByParent,
                $requirements,
                $path,
            );
        }
    }

    /**
     * @param  Collection<string, Collection<int, ItemComponent>>  $componentsByParent
     * @return Collection<int, ItemComponent>
     */
    private function componentsOf(Item $item, Collection $componentsByParent): Collection
    {
        return $componentsByParent
            ->get($item->getKey(), collect())
            ->filter(fn (ItemComponent $component): bool => $component->componentItem !== null)
            ->values();
    }
}
