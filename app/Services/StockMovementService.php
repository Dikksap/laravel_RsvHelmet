<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\ItemComponent;
use App\Models\ProductionBatch;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockMovementService
{
    /**
     * Catat satu transaksi stok dan perbarui saldo gudang.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function record(array $attributes): InventoryMovement
    {
        $quantity = (float) ($attributes['quantity'] ?? 0);

        $this->ensurePositiveQuantity($quantity);

        return DB::transaction(function () use ($attributes, $quantity): InventoryMovement {
            $data = Arr::only($attributes, [
                'type',
                'item_id',
                'warehouse_id',
                'production_batch_id',
                'transfer_group_id',
                'parent_movement_id',
                'occurred_at',
                'reference',
                'note',
                'created_by',
            ]);

            $movement = InventoryMovement::create([
                ...$data,
                'quantity' => $quantity,
                'occurred_at' => $data['occurred_at'] ?? now(),
                'created_by' => $data['created_by'] ?? auth()->id(),
            ]);

            $delta = InventoryMovement::isOutgoingType($movement->type)
                ? -$quantity
                : $quantity;

            $this->applyDelta($movement->item_id, $movement->warehouse_id, $delta);

            return $movement;
        });
    }

    /**
     * Catat hasil produksi. Bila batch dipilih dan barang punya komposisi aktif,
     * material otomatis dikurangi dari gudang material batch.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordProductionOutput(array $attributes): InventoryMovement
    {
        $quantity = (float) ($attributes['quantity'] ?? 0);

        $this->ensurePositiveQuantity($quantity);

        $item = Item::query()
            ->with('components.componentItem')
            ->find($attributes['item_id'] ?? null);

        $batch = filled($attributes['production_batch_id'] ?? null)
            ? ProductionBatch::query()->find($attributes['production_batch_id'])
            : null;

        if (! $item || ! $batch) {
            return $this->record($attributes);
        }

        $components = $item->components
            ->where('is_active', true)
            ->map(fn (ItemComponent $component): array => [
                'item_id' => $component->component_item_id,
                'item' => $component->componentItem,
                'quantity' => round((float) $component->quantity * $quantity, 3),
            ])
            ->filter(fn (array $component): bool => $component['quantity'] > 0)
            ->values();

        if ($components->isEmpty()) {
            return $this->record($attributes);
        }

        $materialWarehouseId = $batch->material_warehouse_id ?? $attributes['warehouse_id'];

        $shortages = [];

        foreach ($components as $component) {
            $onHand = $this->onHand($component['item_id'], $materialWarehouseId);

            if ($onHand < $component['quantity']) {
                $shortages[] = ($component['item']?->item_code ?? $component['item_id'])
                    .' butuh '.Number::format($component['quantity'], maxPrecision: 3, locale: app()->getLocale())
                    .', tersedia '.Number::format($onHand, maxPrecision: 3, locale: app()->getLocale());
            }
        }

        if ($shortages !== []) {
            throw ValidationException::withMessages([
                'quantity' => 'Stok material tidak mencukupi — '.implode('; ', $shortages).'.',
            ]);
        }

        return DB::transaction(function () use ($attributes, $components, $materialWarehouseId): InventoryMovement {
            $output = $this->record($attributes);

            foreach ($components as $component) {
                $this->record([
                    'type' => InventoryMovement::TYPE_OUT_PRODUCTION,
                    'item_id' => $component['item_id'],
                    'warehouse_id' => $materialWarehouseId,
                    'quantity' => $component['quantity'],
                    'production_batch_id' => $output->production_batch_id,
                    'parent_movement_id' => $output->getKey(),
                    'occurred_at' => $output->occurred_at,
                    'reference' => $output->reference,
                    'note' => "Pemakaian otomatis untuk hasil produksi {$output->item->item_code}",
                ]);
            }

            return $output;
        });
    }

    /**
     * Pindahkan stok antar gudang. Menghasilkan dua transaksi: keluar dan masuk.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: InventoryMovement, 1: InventoryMovement}
     */
    public function transfer(array $attributes): array
    {
        $quantity = (float) ($attributes['quantity'] ?? 0);

        $this->ensurePositiveQuantity($quantity);

        $originId = $attributes['warehouse_id'] ?? null;
        $destinationId = $attributes['destination_warehouse_id'] ?? null;

        if (blank($originId) || blank($destinationId)) {
            throw ValidationException::withMessages([
                'destination_warehouse_id' => 'Gudang asal dan gudang tujuan wajib dipilih.',
            ]);
        }

        if ((int) $originId === (int) $destinationId) {
            throw ValidationException::withMessages([
                'destination_warehouse_id' => 'Gudang tujuan harus berbeda dari gudang asal.',
            ]);
        }

        return DB::transaction(function () use ($attributes, $quantity, $originId, $destinationId): array {
            $groupId = (string) Str::uuid();

            $common = [
                'item_id' => $attributes['item_id'],
                'quantity' => $quantity,
                'production_batch_id' => $attributes['production_batch_id'] ?? null,
                'transfer_group_id' => $groupId,
                'occurred_at' => $attributes['occurred_at'] ?? now(),
                'reference' => $attributes['reference'] ?? null,
                'note' => $attributes['note'] ?? null,
                'created_by' => $attributes['created_by'] ?? auth()->id(),
            ];

            $out = InventoryMovement::create([
                ...$common,
                'type' => InventoryMovement::TYPE_TRANSFER_OUT,
                'warehouse_id' => $originId,
            ]);

            $this->applyDelta($out->item_id, $out->warehouse_id, -$quantity);

            $in = InventoryMovement::create([
                ...$common,
                'type' => InventoryMovement::TYPE_TRANSFER_IN,
                'warehouse_id' => $destinationId,
            ]);

            $this->applyDelta($in->item_id, $in->warehouse_id, $quantity);

            return [$out, $in];
        });
    }

    /**
     * Pindahkan beberapa barang sekaligus dari satu gudang asal ke gudang tujuan.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<int, array{0: InventoryMovement, 1: InventoryMovement}>
     */
    public function transferMany(array $attributes): array
    {
        $originId = $attributes['warehouse_id'] ?? null;
        $destinationId = $attributes['destination_warehouse_id'] ?? null;

        if (blank($originId) || blank($destinationId)) {
            throw ValidationException::withMessages([
                'destination_warehouse_id' => 'Gudang asal dan gudang tujuan wajib dipilih.',
            ]);
        }

        if ((int) $originId === (int) $destinationId) {
            throw ValidationException::withMessages([
                'destination_warehouse_id' => 'Gudang tujuan harus berbeda dari gudang asal.',
            ]);
        }

        $quantities = [];

        foreach ((array) ($attributes['items'] ?? []) as $item) {
            $itemId = (string) ($item['item_id'] ?? '');
            $quantity = (float) ($item['quantity'] ?? 0);

            if (blank($itemId) || $quantity <= 0) {
                continue;
            }

            $quantities[$itemId] = ($quantities[$itemId] ?? 0) + $quantity;
        }

        if ($quantities === []) {
            throw ValidationException::withMessages([
                'items' => 'Pilih minimal satu barang dengan jumlah lebih dari 0.',
            ]);
        }

        $itemCodes = Item::query()
            ->whereIn('id', array_keys($quantities))
            ->pluck('item_code', 'id');

        $shortages = [];

        foreach ($quantities as $itemId => $quantity) {
            $onHand = $this->onHand($itemId, $originId);

            if ($onHand < $quantity) {
                $shortages[] = ($itemCodes[$itemId] ?? $itemId)
                    .' butuh '.Number::format($quantity, maxPrecision: 3, locale: app()->getLocale())
                    .', tersedia '.Number::format($onHand, maxPrecision: 3, locale: app()->getLocale());
            }
        }

        if ($shortages !== []) {
            throw ValidationException::withMessages([
                'items' => 'Stok tidak mencukupi — '.implode('; ', $shortages).'.',
            ]);
        }

        $reference = filled($attributes['reference'] ?? null)
            ? $attributes['reference']
            : 'PINDAH-'.now()->format('Ymd-His');

        return DB::transaction(function () use ($attributes, $quantities, $originId, $destinationId, $reference): array {
            $movements = [];

            foreach ($quantities as $itemId => $quantity) {
                $movements[] = $this->transfer([
                    'item_id' => $itemId,
                    'warehouse_id' => $originId,
                    'destination_warehouse_id' => $destinationId,
                    'quantity' => $quantity,
                    'occurred_at' => $attributes['occurred_at'] ?? now(),
                    'reference' => $reference,
                    'note' => $attributes['note'] ?? null,
                    'created_by' => $attributes['created_by'] ?? auth()->id(),
                ]);
            }

            return $movements;
        });
    }

    /**
     * Hapus transaksi dan kembalikan saldo stok. Bila bagian dari pindah gudang,
     * kedua baris (keluar & masuk) dihapus sekaligus.
     */
    public function delete(InventoryMovement $movement): void
    {
        DB::transaction(function () use ($movement): void {
            $movements = $movement->transfer_group_id
                ? InventoryMovement::query()
                    ->where('transfer_group_id', $movement->transfer_group_id)
                    ->get()
                : collect([$movement]);

            $children = InventoryMovement::query()
                ->whereIn('parent_movement_id', $movements->pluck('id')->all())
                ->get();

            foreach ($children as $child) {
                $this->reverse($child);
                $child->delete();
            }

            foreach ($movements as $item) {
                $this->reverse($item);
                $item->delete();
            }
        });
    }

    private function reverse(InventoryMovement $movement): void
    {
        $quantity = (float) $movement->quantity;

        if ($movement->isIncoming()) {
            $onHand = $this->onHand($movement->item_id, $movement->warehouse_id);

            if ($onHand < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Transaksi tidak dapat dihapus karena stok '
                        .($movement->item?->item_name ?? $movement->item_id)
                        .' sudah terpakai. Stok saat ini '
                        .Number::format($onHand, maxPrecision: 3, locale: app()->getLocale())
                        .', sedangkan pembatalan membutuhkan '
                        .Number::format($quantity, maxPrecision: 3, locale: app()->getLocale())
                        .'.',
                ]);
            }
        }

        $delta = $movement->isIncoming() ? -1 * $quantity : $quantity;

        $this->applyDelta($movement->item_id, $movement->warehouse_id, $delta);
    }

    private function onHand(string $itemId, int|string|null $warehouseId): float
    {
        if (blank($warehouseId)) {
            return 0.0;
        }

        return (float) InventoryBalance::query()
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->value('quantity_on_hand');
    }

    private function applyDelta(string $itemId, int|string $warehouseId, float $delta): void
    {
        /** @var InventoryBalance|null $balance */
        $balance = InventoryBalance::query()
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            $balance = new InventoryBalance([
                'item_id' => $itemId,
                'warehouse_id' => $warehouseId,
                'quantity_on_hand' => 0,
                'minimum_quantity' => 0,
            ]);
        }

        $current = (float) $balance->quantity_on_hand;
        $newQuantity = $current + $delta;

        if ($newQuantity < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Stok tidak mencukupi. Stok saat ini '.Number::format($current, maxPrecision: 3, locale: app()->getLocale()).'.',
            ]);
        }

        $balance->quantity_on_hand = $newQuantity;
        $balance->save();
    }

    private function ensurePositiveQuantity(float $quantity): void
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Jumlah harus lebih besar dari 0.',
            ]);
        }
    }
}
