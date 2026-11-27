<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryLine;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    /**
     * Buat dokumen pengiriman baru sebagai draft.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Delivery
    {
        return DB::transaction(fn (): Delivery => Delivery::create([
            'warehouse_id' => $attributes['warehouse_id'],
            'recipient_name' => $attributes['recipient_name'],
            'delivery_date' => $attributes['delivery_date'] ?? now()->toDateString(),
            'status' => Delivery::STATUS_DRAFT,
            'note' => $attributes['note'] ?? null,
            'created_by' => auth()->id(),
        ]));
    }

    /**
     * Kirim barang: catat transaksi stok keluar dari gudang asal lalu kunci dokumen.
     */
    public function ship(Delivery $delivery): Delivery
    {
        return DB::transaction(function () use ($delivery): Delivery {
            /** @var Delivery $locked */
            $locked = Delivery::query()->lockForUpdate()->findOrFail($delivery->getKey());

            if (! $locked->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya pengiriman berstatus draft yang bisa dikirim.',
                ]);
            }

            /** @var Collection<int, DeliveryLine> $lines */
            $lines = $locked->lines()->with('item')->lockForUpdate()->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => 'Tambahkan minimal satu barang sebelum mengirim.',
                ]);
            }

            $quantities = [];

            foreach ($lines as $line) {
                $quantity = (float) $line->quantity;

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'lines' => 'Jumlah barang '.$this->lineLabel($line).' harus lebih besar dari 0.',
                    ]);
                }

                $quantities[$line->item_id] = ($quantities[$line->item_id] ?? 0) + $quantity;
            }

            $shortages = [];

            foreach ($quantities as $itemId => $quantity) {
                $onHand = (float) InventoryBalance::query()
                    ->where('warehouse_id', $locked->warehouse_id)
                    ->where('item_id', $itemId)
                    ->lockForUpdate()
                    ->value('quantity_on_hand');

                if ($onHand < $quantity) {
                    $shortages[] = $this->itemLabel($itemId)
                        .' butuh '.Number::format($quantity, maxPrecision: 3, locale: app()->getLocale())
                        .', tersedia '.Number::format($onHand, maxPrecision: 3, locale: app()->getLocale());
                }
            }

            if ($shortages !== []) {
                throw ValidationException::withMessages([
                    'lines' => 'Stok tidak mencukupi — '.implode('; ', $shortages).'.',
                ]);
            }

            $occurredAt = now();
            $movementService = app(StockMovementService::class);

            foreach ($lines as $line) {
                $movementService->record([
                    'type' => InventoryMovement::TYPE_OUT_DELIVERY,
                    'item_id' => $line->item_id,
                    'warehouse_id' => $locked->warehouse_id,
                    'quantity' => (float) $line->quantity,
                    'delivery_id' => $locked->getKey(),
                    'occurred_at' => $occurredAt,
                    'reference' => $locked->code,
                    'note' => 'Pengiriman ke '.$locked->recipient_name,
                    'created_by' => auth()->id(),
                ]);
            }

            $locked->update([
                'status' => Delivery::STATUS_SHIPPED,
                'shipped_at' => $occurredAt,
                'shipped_by' => auth()->id(),
            ]);

            return $locked->refresh();
        });
    }

    /**
     * Batalkan pengiriman. Pengiriman yang sudah dikirim akan mengembalikan stok.
     */
    public function cancel(Delivery $delivery): Delivery
    {
        return DB::transaction(function () use ($delivery): Delivery {
            /** @var Delivery $locked */
            $locked = Delivery::query()->lockForUpdate()->findOrFail($delivery->getKey());

            if (! $locked->isDraft() && ! $locked->isShipped()) {
                throw ValidationException::withMessages([
                    'status' => 'Pengiriman ini sudah dibatalkan.',
                ]);
            }

            if ($locked->isShipped()) {
                $movementService = app(StockMovementService::class);

                foreach ($locked->movements()->get() as $movement) {
                    $movementService->delete($movement);
                }
            }

            $locked->update([
                'status' => Delivery::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
            ]);

            return $locked->refresh();
        });
    }

    private function itemLabel(string $itemId): string
    {
        return (string) Item::query()->whereKey($itemId)->value('item_code') ?: $itemId;
    }

    private function lineLabel(DeliveryLine $line): string
    {
        return $line->item?->item_code
            ?? $line->item?->item_name
            ?? 'baris #'.$line->getKey();
    }
}
