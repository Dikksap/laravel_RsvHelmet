@inject('apiService', 'App\Services\ExternalBarangApiService')

@php
    $data = $this->getGroupedData($apiService);
    $groups = $data['groups'];
@endphp

<x-filament-panels::page>
    {{-- Form Filter Ringkas --}}
    <div class="space-y-3">
        {{ $this->filterForm }}

        <div class="flex items-center gap-2">
            <x-filament::button wire:click="applyFilter" size="sm" icon="heroicon-m-funnel">
                Terapkan
            </x-filament::button>

            <x-filament::button wire:click="resetFilter" size="sm" color="gray">
                Reset
            </x-filament::button>
        </div>
    </div>

    {{-- Ringkasan Statistik Compact --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="p-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg">
            <div class="text-xs text-gray-500 dark:text-gray-400">Total Hari</div>
            <div class="text-xl font-bold text-gray-900 dark:text-white">{{ count($groups) }}</div>
        </div>

        <div class="p-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg">
            <div class="text-xs text-gray-500 dark:text-gray-400">Total Unit</div>
            <div class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($data['overall_total']) }}</div>
        </div>

        <div class="p-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg">
            <div class="text-xs text-emerald-600 dark:text-emerald-400">Finish Good</div>
            <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($data['overall_finishgood']) }}</div>
        </div>

        <div class="p-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg">
            <div class="text-xs text-blue-600 dark:text-blue-400">Masuk Stok RSV</div>
            <div class="text-xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($data['overall_synced']) }}</div>
        </div>
    </div>

    {{-- Daftar Grouping Berdasarkan Tanggal --}}
    @if(empty($groups))
        <div class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
            Tidak ada data barang eksternal untuk filter ini.
        </div>
    @else
        <div class="space-y-4">
            @foreach($groups as $tanggal => $group)
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden shadow-sm">
                    {{-- Header Tanggal Simple --}}
                    <div class="px-4 py-2 bg-gray-50/80 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-sm text-gray-900 dark:text-white">{{ $group['tanggal_label'] }}</span>
                            <span class="text-gray-400 font-mono">({{ $tanggal }})</span>
                        </div>

                        <div class="text-gray-600 dark:text-gray-300 space-x-2">
                            <span>Total: <strong class="text-gray-900 dark:text-white">{{ number_format($group['total_units']) }}</strong></span>
                            <span>·</span>
                            <span>Finish Good: <strong class="text-emerald-600 dark:text-emerald-400">{{ number_format($group['total_finishgood']) }}</strong></span>
                            <span>·</span>
                            <span>Register: <strong class="text-gray-500">{{ number_format($group['total_register']) }}</strong></span>
                            <span>·</span>
                            <span>Masuk Stok: <strong class="text-blue-600 dark:text-blue-400">{{ number_format($group['total_synced']) }}</strong></span>
                        </div>
                    </div>

                    {{-- Tabel Compact --}}
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs divide-y divide-gray-100 dark:divide-gray-800">
                            <thead>
                                <tr class="text-gray-500 dark:text-gray-400 font-medium">
                                    <th class="py-2 px-3">Varian</th>
                                    <th class="py-2 px-3">Spesifikasi</th>
                                    <th class="py-2 px-3">Pemetaan Barang RSV</th>
                                    <th class="py-2 px-3">Rincian Per Status</th>
                                    <th class="py-2 px-3 text-right">Total</th>
                                    <th class="py-2 px-3 text-right">Masuk Stok</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($group['variants'] as $v)
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                                        {{-- Varian --}}
                                        <td class="py-2 px-3 font-semibold text-gray-900 dark:text-white whitespace-nowrap">
                                            {{ $v['kode_variant'] }}
                                        </td>

                                        {{-- Spesifikasi Helm --}}
                                        <td class="py-2 px-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                            {{ $v['product'] }} {{ $v['style'] }} · {{ $v['color'] }} ({{ $v['size'] }})
                                        </td>

                                        {{-- Pemetaan Barang RSV (TANPA KOTAK) --}}
                                        <td class="py-2 px-3 text-gray-800 dark:text-gray-200">
                                            @if($v['mapping'] && $v['mapping']->item)
                                                <span class="font-medium text-emerald-700 dark:text-emerald-400">{{ $v['mapping']->item->item_code }}</span>
                                                <span class="text-gray-600 dark:text-gray-400">· {{ $v['mapping']->item->item_name }}</span>
                                            @else
                                                <span class="text-amber-600 dark:text-amber-400">Belum Dimapping</span>
                                            @endif
                                        </td>

                                        {{-- Rincian Per Status (TANPA KOTAK) --}}
                                        <td class="py-2 px-3">
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                                @foreach($v['per_status'] as $statusName => $count)
                                                    @if($count > 0)
                                                        <span class="whitespace-nowrap">
                                                            <span class="text-gray-500 dark:text-gray-400">{{ $statusName }}:</span>
                                                            <span class="font-medium text-gray-900 dark:text-gray-200">{{ $count }}</span>
                                                        </span>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </td>

                                        {{-- Total Unit --}}
                                        <td class="py-2 px-3 text-right font-semibold text-gray-900 dark:text-white whitespace-nowrap">
                                            {{ number_format($v['total']) }}
                                        </td>

                                        {{-- Status Masuk Stok --}}
                                        <td class="py-2 px-3 text-right whitespace-nowrap">
                                            @if($v['synced_count'] >= $v['finishgood_total'] && $v['finishgood_total'] > 0)
                                                <span class="text-emerald-600 dark:text-emerald-400 font-medium">Lengkap ({{ $v['synced_count'] }})</span>
                                            @elseif($v['synced_count'] > 0)
                                                <span class="text-blue-600 dark:text-blue-400 font-medium">{{ $v['synced_count'] }}/{{ $v['finishgood_total'] }}</span>
                                            @elseif($v['finishgood_total'] > 0)
                                                <span class="text-gray-400">0/{{ $v['finishgood_total'] }}</span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
