@php
    $warehouses = $this->getWarehouses();
    $format = fn (mixed $value): string => \Illuminate\Support\Number::format(
        (float) $value,
        maxPrecision: 3,
        locale: app()->getLocale(),
    );
@endphp

<x-filament-panels::page>
    @if ($warehouses->isEmpty())
        <x-filament::section
            heading="Belum ada gudang"
            description="Tambahkan gudang terlebih dahulu untuk mulai mencatat stok."
            icon="heroicon-o-building-storefront"
        >
            <a
                href="{{ \App\Filament\Resources\Warehouses\WarehouseResource::getUrl('create') }}"
                class="inline-flex items-center gap-x-1.5 text-sm font-semibold text-amber-600 hover:text-amber-500 dark:text-amber-400"
            >
                Tambah gudang
                <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" />
            </a>
        </x-filament::section>
    @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($warehouses as $warehouse)
                <a
                    href="{{ \App\Filament\Pages\WarehouseStockDetail::getUrl(['warehouse' => $warehouse]) }}"
                    @class([
                        'group flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-amber-400 hover:shadow-md dark:border-white/10 dark:bg-white/5 dark:hover:border-amber-500/70',
                        'opacity-60' => ! $warehouse->is_active,
                    ])
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $warehouse->name }}
                                </span>

                                @unless ($warehouse->is_active)
                                    <x-filament::badge color="gray" size="sm">Nonaktif</x-filament::badge>
                                @endunless
                            </div>

                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {{ $warehouse->code }}
                            </div>

                            @if (filled($warehouse->address))
                                <div class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
                                    {{ $warehouse->address }}
                                </div>
                            @endif
                        </div>

                        <x-filament::icon
                            icon="heroicon-o-chevron-right"
                            class="mt-0.5 h-4 w-4 flex-none text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-amber-500"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Jenis barang</div>
                            <div class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $format($warehouse->total_items) }}
                            </div>
                        </div>

                        <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Total stok</div>
                            <div class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $format($warehouse->total_quantity ?? 0) }}
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5">
                        @if ((int) $warehouse->low_stock_count > 0)
                            <x-filament::badge color="warning" size="sm">
                                {{ $warehouse->low_stock_count }} menipis
                            </x-filament::badge>
                        @endif

                        @if ((int) $warehouse->out_of_stock_count > 0)
                            <x-filament::badge color="danger" size="sm">
                                {{ $warehouse->out_of_stock_count }} habis
                            </x-filament::badge>
                        @endif

                        @if ((int) $warehouse->low_stock_count === 0 && (int) $warehouse->out_of_stock_count === 0)
                            @if ((int) $warehouse->total_items === 0)
                                <x-filament::badge color="gray" size="sm">Belum ada stok</x-filament::badge>
                            @else
                                <x-filament::badge color="success" size="sm">Stok aman</x-filament::badge>
                            @endif
                        @endif

                        <span class="ms-auto text-xs font-semibold text-amber-600 dark:text-amber-400">
                            Lihat stok
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
