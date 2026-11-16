@use('App\Models\ProductionBatch')
@use('Illuminate\Support\Number')

<x-filament-panels::page>
    @php
        $outputs = $this->outputRealisations();
        $materials = $this->materialRealisations();

        $totalPlanned = array_sum(array_column($outputs, 'planned'));
        $totalActual = array_sum(array_column($outputs, 'actual'));
        $overallPercentage = $this->percentage($totalPlanned, $totalActual);

        $totalRequired = array_sum(array_column($materials, 'required'));
        $totalUsed = array_sum(array_column($materials, 'used'));

        $format = fn (float|int|null $value): string => Number::format(
            (float) $value,
            maxPrecision: 3,
            locale: app()->getLocale(),
        );

        $signed = fn (float $value): string => ($value > 0 ? '+' : '').$format($value);
    @endphp

    <style>
        @media print {
            .fi-sidebar,
            .fi-topbar,
            .fi-header-actions-ctn,
            .fi-breadcrumbs,
            .fi-layout-sidebar-toggle-btn-ctn,
            .fi-sidebar-close-overlay {
                display: none !important;
            }

            .fi-main {
                max-width: none !important;
                padding: 0 !important;
            }

            .fi-body {
                background-color: #fff !important;
            }

            .fi-section {
                border: none !important;
                box-shadow: none !important;
            }
        }
    </style>

    <x-filament::section
        heading="Ringkasan batch"
        icon="heroicon-o-information-circle"
    >
        <div class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <div class="text-gray-500 dark:text-gray-400">Kode batch</div>
                <div class="font-semibold text-gray-950 dark:text-white">{{ $this->batch->batch_code }}</div>
            </div>
            <div>
                <div class="text-gray-500 dark:text-gray-400">Status</div>
                <div>
                    <x-filament::badge
                        :color="match ($this->batch->status) {
                            ProductionBatch::STATUS_RUNNING => 'info',
                            ProductionBatch::STATUS_COMPLETED => 'success',
                            ProductionBatch::STATUS_CANCELLED => 'danger',
                            default => 'gray',
                        }"
                    >
                        {{ $this->batch->status_label }}
                    </x-filament::badge>
                </div>
            </div>
            <div>
                <div class="text-gray-500 dark:text-gray-400">Dibuat oleh</div>
                <div class="text-gray-950 dark:text-white">{{ $this->batch->creator?->name ?? '—' }}</div>
            </div>
            <div>
                <div class="text-gray-500 dark:text-gray-400">Gudang material</div>
                <div class="text-gray-950 dark:text-white">{{ $this->batch->materialWarehouse?->name ?? '—' }}</div>
            </div>
            <div>
                <div class="text-gray-500 dark:text-gray-400">Gudang hasil produksi</div>
                <div class="text-gray-950 dark:text-white">{{ $this->batch->outputWarehouse?->name ?? '—' }}</div>
            </div>
            <div>
                <div class="text-gray-500 dark:text-gray-400">Tanggal</div>
                <div class="text-gray-950 dark:text-white">
                    {{ $this->batch->started_at?->format('d/m/Y') ?? '—' }}
                    &rarr;
                    {{ $this->batch->finished_at?->format('d/m/Y') ?? '—' }}
                </div>
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <div class="text-gray-500 dark:text-gray-400">Catatan</div>
                <div class="text-gray-950 dark:text-white">{{ $this->batch->notes ?: '—' }}</div>
            </div>
        </div>

        <div class="mt-6 grid gap-4 text-sm sm:grid-cols-3">
            <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                <div class="text-gray-500 dark:text-gray-400">Rencana hasil</div>
                <div class="text-lg font-semibold text-gray-950 dark:text-white">{{ $format($totalPlanned) }}</div>
            </div>
            <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                <div class="text-gray-500 dark:text-gray-400">Realisasi hasil</div>
                <div class="text-lg font-semibold text-gray-950 dark:text-white">{{ $format($totalActual) }}</div>
            </div>
            <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                <div class="text-gray-500 dark:text-gray-400">Tercapai</div>
                <div class="text-lg font-semibold text-gray-950 dark:text-white">{{ $format($overallPercentage) }}%</div>
            </div>
        </div>

        <div class="mt-4">
            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                <div
                    class="h-2 rounded-full"
                    style="width: {{ min($overallPercentage, 100) }}%; background-color: var(--primary-500);"
                ></div>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section
        heading="Hasil produksi"
        description="Rencana vs realisasi jumlah barang jadi yang dicatat untuk batch ini."
        icon="heroicon-o-cube"
    >
        @if (empty($outputs))
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Belum ada produk yang direncanakan pada batch ini.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-2 font-medium">Kode</th>
                            <th class="px-3 py-2 font-medium">Barang</th>
                            <th class="px-3 py-2 text-right font-medium">Rencana</th>
                            <th class="px-3 py-2 text-right font-medium">Realisasi</th>
                            <th class="px-3 py-2 text-right font-medium">Selisih</th>
                            <th class="px-3 py-2 text-right font-medium">Tercapai</th>
                            <th class="px-3 py-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($outputs as $row)
                            @php
                                $status = match (true) {
                                    $row['actual'] <= 0 => 'Belum tercatat',
                                    $row['actual'] + 0.0001 < $row['planned'] => 'Sebagian',
                                    $row['actual'] > $row['planned'] + 0.0001 => 'Melebihi rencana',
                                    default => 'Sesuai rencana',
                                };
                            @endphp
                            <tr>
                                <td class="px-3 py-2 text-gray-950 dark:text-white">{{ $row['item']?->item_code ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-950 dark:text-white">{{ $row['item']?->item_name ?? '—' }}</td>
                                <td class="px-3 py-2 text-right text-gray-950 dark:text-white">{{ $format($row['planned']) }}</td>
                                <td class="px-3 py-2 text-right text-gray-950 dark:text-white">{{ $format($row['actual']) }}</td>
                                <td @class([
                                    'px-3 py-2 text-right font-medium',
                                    'text-danger-600 dark:text-danger-400' => $row['difference'] < 0,
                                    'text-success-600 dark:text-success-400' => $row['difference'] >= 0,
                                ])>{{ $signed($row['difference']) }}</td>
                                <td class="px-3 py-2 text-right text-gray-950 dark:text-white">{{ $format($row['percentage']) }}%</td>
                                <td class="px-3 py-2">
                                    <x-filament::badge
                                        :color="match ($status) {
                                            'Belum tercatat' => 'gray',
                                            'Sebagian' => 'warning',
                                            'Melebihi rencana' => 'info',
                                            default => 'success',
                                        }"
                                    >
                                        {{ $status }}
                                    </x-filament::badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section
        heading="Realisasi material"
        description="Kebutuhan material dari komposisi vs pemakaian yang tercatat untuk batch ini."
        icon="heroicon-o-squares-2x2"
    >
        @if (empty($materials))
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Belum ada kebutuhan material. Pastikan produk pada batch memiliki komposisi barang.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-2 font-medium">Kode</th>
                            <th class="px-3 py-2 font-medium">Material</th>
                            <th class="px-3 py-2 text-right font-medium">Kebutuhan</th>
                            <th class="px-3 py-2 text-right font-medium">Terpakai</th>
                            <th class="px-3 py-2 text-right font-medium">Sisa kebutuhan</th>
                            <th class="px-3 py-2 font-medium">Kekurangan stok</th>
                            <th class="px-3 py-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($materials as $row)
                            @php
                                $status = match (true) {
                                    $row['used'] <= 0 => 'Belum dipakai',
                                    $row['used'] + 0.0001 < $row['required'] => 'Sebagian',
                                    $row['used'] > $row['required'] + 0.0001 => 'Melebihi kebutuhan',
                                    default => 'Sesuai kebutuhan',
                                };
                            @endphp
                            <tr>
                                <td class="px-3 py-2 text-gray-950 dark:text-white">{{ $row['item']?->item_code ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-950 dark:text-white">{{ $row['item']?->item_name ?? '—' }}</td>
                                <td class="px-3 py-2 text-right text-gray-950 dark:text-white">{{ $format($row['required']) }}</td>
                                <td class="px-3 py-2 text-right text-gray-950 dark:text-white">{{ $format($row['used']) }}</td>
                                <td class="px-3 py-2 text-right text-gray-950 dark:text-white">{{ $format($row['remaining']) }}</td>
                                <td class="px-3 py-2">
                                    <x-filament::badge :color="$row['shortage'] > 0 ? 'danger' : 'success'">
                                        {{ $row['shortage'] > 0 ? $format($row['shortage']) : 'Cukup' }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-3 py-2">
                                    <x-filament::badge
                                        :color="match ($status) {
                                            'Belum dipakai' => 'gray',
                                            'Sebagian' => 'warning',
                                            'Melebihi kebutuhan' => 'info',
                                            default => 'success',
                                        }"
                                    >
                                        {{ $status }}
                                    </x-filament::badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-gray-200 dark:border-white/10">
                        <tr class="font-semibold text-gray-950 dark:text-white">
                            <td class="px-3 py-2" colspan="2">Total</td>
                            <td class="px-3 py-2 text-right">{{ $format($totalRequired) }}</td>
                            <td class="px-3 py-2 text-right">{{ $format($totalUsed) }}</td>
                            <td class="px-3 py-2 text-right">{{ $format(max($totalRequired - $totalUsed, 0)) }}</td>
                            <td class="px-3 py-2" colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
