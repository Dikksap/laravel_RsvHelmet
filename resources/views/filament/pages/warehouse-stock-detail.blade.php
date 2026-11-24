@php
    $summary = $this->getSummary();
    $format = fn (mixed $value): string => \Illuminate\Support\Number::format(
        (float) $value,
        maxPrecision: 3,
        locale: app()->getLocale(),
    );
@endphp

<x-filament-panels::page>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="text-xs text-gray-500 dark:text-gray-400">Jenis barang</div>
            <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                {{ $format($summary['total_items']) }}
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="text-xs text-gray-500 dark:text-gray-400">Total stok</div>
            <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                {{ $format($summary['total_quantity']) }}
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="text-xs text-gray-500 dark:text-gray-400">Stok menipis</div>
            <div @class([
                'mt-1 text-lg font-semibold',
                'text-amber-600 dark:text-amber-400' => $summary['low_stock_count'] > 0,
                'text-gray-950 dark:text-white' => $summary['low_stock_count'] === 0,
            ])>
                {{ $format($summary['low_stock_count']) }}
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="text-xs text-gray-500 dark:text-gray-400">Stok habis</div>
            <div @class([
                'mt-1 text-lg font-semibold',
                'text-danger-600 dark:text-danger-400' => $summary['out_of_stock_count'] > 0,
                'text-gray-950 dark:text-white' => $summary['out_of_stock_count'] === 0,
            ])>
                {{ $format($summary['out_of_stock_count']) }}
            </div>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
