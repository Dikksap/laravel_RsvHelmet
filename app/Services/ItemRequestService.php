<?php

namespace App\Services;

use App\Models\ItemRequest;
use App\Models\ItemRequestLine;
use App\Models\ItemRequestSetting;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItemRequestService
{
    /**
     * Setujui permintaan dan pindahkan stok barang yang diminta antar gudang.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function approve(ItemRequest $itemRequest, array $attributes): ItemRequest
    {
        return DB::transaction(function () use ($itemRequest, $attributes): ItemRequest {
            /** @var ItemRequest $locked */
            $locked = ItemRequest::query()->lockForUpdate()->findOrFail($itemRequest->getKey());

            $this->ensurePending($locked);

            $settings = ItemRequestSetting::current();

            $originId = $attributes['warehouse_id'] ?? $settings->warehouse_id;
            $destinationId = $attributes['destination_warehouse_id'] ?? $settings->destination_warehouse_id;

            if (blank($originId) || blank($destinationId)) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Gudang asal dan gudang tujuan belum diatur. Atur terlebih dahulu di menu Pengaturan Permintaan.',
                ]);
            }

            if ((int) $originId === (int) $destinationId) {
                throw ValidationException::withMessages([
                    'destination_warehouse_id' => 'Gudang tujuan harus berbeda dari gudang asal.',
                ]);
            }

            $activeWarehouseCount = Warehouse::query()
                ->where('is_active', true)
                ->whereIn('id', [$originId, $destinationId])
                ->count();

            if ($activeWarehouseCount !== 2) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Gudang asal dan gudang tujuan harus aktif.',
                ]);
            }

            $lines = $locked->lines()->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => 'Permintaan ini tidak memiliki barang untuk ditransfer.',
                ]);
            }

            /** @var Collection<int, ItemRequestLine> $unmapped */
            $unmapped = $lines->filter(fn (ItemRequestLine $line): bool => blank($line->item_id));

            if ($unmapped->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => 'Semua barang permintaan harus dipetakan sebelum disetujui. '
                        .$unmapped->count().' baris belum dipetakan.',
                ]);
            }

            app(StockMovementService::class)->transferMany([
                'warehouse_id' => $originId,
                'destination_warehouse_id' => $destinationId,
                'occurred_at' => $attributes['occurred_at'] ?? now(),
                'reference' => $locked->code,
                'note' => $attributes['note'] ?? null,
                'item_request_id' => $locked->getKey(),
                'created_by' => auth()->id(),
                'items' => $lines
                    ->map(fn (ItemRequestLine $line): array => [
                        'item_id' => $line->item_id,
                        'quantity' => (float) $line->quantity,
                    ])
                    ->all(),
            ]);

            $locked->update([
                'status' => ItemRequest::STATUS_COMPLETED,
                'warehouse_id' => $originId,
                'destination_warehouse_id' => $destinationId,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_note' => null,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * Tolak permintaan tanpa mengubah stok gudang.
     */
    public function reject(ItemRequest $itemRequest, string $reason): ItemRequest
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'rejection_note' => 'Alasan penolakan wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($itemRequest, $reason): ItemRequest {
            /** @var ItemRequest $locked */
            $locked = ItemRequest::query()->lockForUpdate()->findOrFail($itemRequest->getKey());

            $this->ensurePending($locked);

            $locked->update([
                'status' => ItemRequest::STATUS_REJECTED,
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'rejection_note' => $reason,
            ]);

            return $locked->refresh();
        });
    }

    private function ensurePending(ItemRequest $itemRequest): void
    {
        if (! $itemRequest->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Hanya permintaan berstatus baru yang bisa diproses.',
            ]);
        }
    }
}
