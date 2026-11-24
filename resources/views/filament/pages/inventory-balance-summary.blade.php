@use('Illuminate\Support\Number')

<x-filament-panels::page>
    @php
        $stats = $this->getStockStats();

        $format = fn (float|int|null $value): string => Number::format(
            (float) $value,
            maxPrecision: 3,
            locale: app()->getLocale(),
        );
    @endphp

    {{-- Ringkasan Statistik Compact --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs text-gray-500 dark:text-gray-400">Barang terdaftar</div>
            <div class="text-xl font-bold text-gray-950 dark:text-white">{{ $format($stats['items']) }}</div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs text-primary-600 dark:text-primary-400">Total unit stok</div>
            <div class="text-xl font-bold text-primary-600 dark:text-primary-400">{{ $format($stats['total_units']) }}</div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs text-emerald-600 dark:text-emerald-400">Barang tersedia</div>
            <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ $format($stats['stocked']) }}</div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs text-danger-600 dark:text-danger-400">Barang kosong</div>
            <div class="text-xl font-bold text-danger-600 dark:text-danger-400">{{ $format($stats['out_of_stock']) }}</div>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
