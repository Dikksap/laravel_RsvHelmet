<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\StockOpname;
use App\Models\StockOpnameLine;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockOpnameService
{
    /**
     * Buat dokumen stok opname dan snapshot semua barang yang bisa distok.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(Warehouse $warehouse, array $attributes = []): StockOpname
    {
        return DB::transaction(function () use ($warehouse, $attributes): StockOpname {
            $opname = StockOpname::create([
                'code' => $this->generateCode($warehouse),
                'warehouse_id' => $warehouse->getKey(),
                'status' => StockOpname::STATUS_DRAFT,
                'note' => $attributes['note'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $balances = InventoryBalance::query()
                ->where('warehouse_id', $warehouse->getKey())
                ->pluck('quantity_on_hand', 'item_id');

            Item::query()
                ->where('is_stockable', true)
                ->where('has_variants', false)
                ->orderBy('item_name')
                ->select(['id'])
                ->chunk(500, function ($items) use ($opname, $balances): void {
                    $timestamp = now();

                    StockOpnameLine::query()->insert(
                        $items->map(fn (Item $item): array => [
                            'stock_opname_id' => $opname->getKey(),
                            'item_id' => $item->getKey(),
                            'system_quantity' => (float) ($balances[$item->getKey()] ?? 0),
                            'counted_quantity' => null,
                            'adjustment_quantity' => null,
                            'counted_at' => null,
                            'counted_by' => null,
                            'note' => null,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ])->all(),
                    );
                });

            return $opname;
        });
    }

    /**
     * Posting hitung fisik: buat transaksi penyesuaian dan kunci dokumen.
     */
    public function post(StockOpname $opname): StockOpname
    {
        return DB::transaction(function () use ($opname): StockOpname {
            /** @var StockOpname $locked */
            $locked = StockOpname::query()->lockForUpdate()->findOrFail($opname->getKey());

            if (! $locked->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Stok opname ini sudah selesai dan tidak bisa diposting ulang.',
                ]);
            }

            $lines = $locked->lines()
                ->whereNotNull('counted_quantity')
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => 'Isi minimal satu jumlah fisik sebelum melakukan posting.',
                ]);
            }

            $completedAt = now();
            $movementService = app(StockMovementService::class);

            foreach ($lines as $line) {
                $balance = InventoryBalance::query()
                    ->where('warehouse_id', $locked->warehouse_id)
                    ->where('item_id', $line->item_id)
                    ->lockForUpdate()
                    ->first();

                $currentQuantity = (float) ($balance?->quantity_on_hand ?? 0);
                $delta = round((float) $line->counted_quantity - $currentQuantity, 3);

                if (abs($delta) >= 0.0005) {
                    $movementService->record([
                        'type' => $delta > 0
                            ? InventoryMovement::TYPE_ADJUSTMENT_IN
                            : InventoryMovement::TYPE_ADJUSTMENT_OUT,
                        'item_id' => $line->item_id,
                        'warehouse_id' => $locked->warehouse_id,
                        'quantity' => abs($delta),
                        'stock_opname_id' => $locked->getKey(),
                        'occurred_at' => $completedAt,
                        'reference' => $locked->code,
                        'note' => 'Penyesuaian stok opname '.$locked->code,
                        'created_by' => auth()->id(),
                    ]);
                }

                $line->update(['adjustment_quantity' => $delta]);

                InventoryBalance::query()
                    ->where('warehouse_id', $locked->warehouse_id)
                    ->where('item_id', $line->item_id)
                    ->update(['last_counted_at' => $completedAt]);
            }

            $locked->update([
                'status' => StockOpname::STATUS_COMPLETED,
                'completed_at' => $completedAt,
                'completed_by' => auth()->id(),
            ]);

            return $locked->refresh();
        });
    }

    /**
     * Batalkan dokumen yang belum diposting.
     */
    public function cancel(StockOpname $opname): StockOpname
    {
        return DB::transaction(function () use ($opname): StockOpname {
            /** @var StockOpname $locked */
            $locked = StockOpname::query()->lockForUpdate()->findOrFail($opname->getKey());

            if (! $locked->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya stok opname berstatus draft yang bisa dibatalkan.',
                ]);
            }

            $locked->update(['status' => StockOpname::STATUS_CANCELLED]);

            return $locked->refresh();
        });
    }

    private function generateCode(Warehouse $warehouse): string
    {
        $prefix = 'OPNAME-'.$warehouse->code.'-'.now()->format('Ymd-His');
        $code = $prefix;
        $suffix = 2;

        while (StockOpname::query()->where('code', $code)->exists()) {
            $code = $prefix.'-'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
