<div class="space-y-4">
    <!-- Page Heading -->
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Formulir Permintaan Barang</h1>
            <p class="text-xs text-slate-500">Isi data pemohon dan barang yang dibutuhkan.</p>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600">
            <span class="size-1.5 rounded-full bg-emerald-500"></span>
            {{ now()->translatedFormat('d M Y') }}
        </span>
    </div>

    @if ($submitted)
        <!-- Submission Confirmation -->
        <div class="overflow-hidden rounded-xl border border-emerald-200 bg-white shadow-xs">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-emerald-100 bg-emerald-50/70 px-4 py-3.5 sm:px-5">
                <div class="flex items-center gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white shadow-xs">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-emerald-900">Permintaan terkirim</p>
                        <p class="text-xs text-emerald-700">
                            Nomor tiket
                            <span class="font-mono font-bold tracking-wide text-emerald-900">{{ $submittedCode }}</span>
                            &bull; Menunggu diproses
                        </p>
                    </div>
                </div>

                <div x-data="{ copied: false }">
                    <button
                        type="button"
                        @click="navigator.clipboard.writeText('{{ $submittedCode }}'); copied = true; setTimeout(() => copied = false, 2500)"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-800 shadow-2xs transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-300"
                    >
                        <svg x-show="!copied" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                        </svg>
                        <svg x-cloak x-show="copied" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        <span x-show="!copied">Salin nomor</span>
                        <span x-cloak x-show="copied">Tersalin</span>
                    </button>
                </div>
            </div>

            <div class="space-y-3 p-4 sm:p-5">
                @if ($submittedRequest)
                    <dl class="divide-y divide-slate-100 rounded-lg border border-slate-200">
                        @foreach ($submittedRequest->lines as $line)
                            <div class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                                <dt class="min-w-0 truncate text-slate-700">
                                    {{ $line->item?->item_name ?? $line->requested_item_name }}
                                </dt>
                                <dd class="shrink-0 font-semibold text-slate-900">
                                    {{ $line->formattedQuantity() }} <span class="text-xs font-normal text-slate-500">{{ $line->item?->baseUom?->code }}</span>
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                @if ($submittedRequest && $pdfUrl && $shareMessage)
                    <details class="group rounded-lg border border-slate-200">
                        <summary class="flex cursor-pointer list-none items-center justify-between px-3 py-2 text-xs font-semibold text-slate-700 [&::-webkit-details-marker]:hidden">
                            <span>Pratinjau dokumen permintaan</span>
                            <svg class="size-4 text-slate-400 transition group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </summary>
                        <div class="border-t border-slate-200 bg-slate-50 p-2">
                            @include('livewire.partials.item-request-preview', ['submittedRequest' => $submittedRequest])
                        </div>
                    </details>
                @endif

                <div class="flex flex-wrap items-center gap-2">
                    @if ($pdfUrl && $submittedRequest)
                        <a
                            href="{{ $pdfUrl }}"
                            download="Permintaan-{{ $submittedRequest->code }}.pdf"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300"
                        >
                            <svg class="size-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Unduh PDF
                        </a>

                        <div
                            x-data="{ isPreparingPdf: true, isPdfReady: false, isSharing: false, shareError: '' }"
                            x-init="window.prepareItemRequestPdf(@js($pdfUrl), @js($submittedRequest->code)).then(() => { isPdfReady = true; isPreparingPdf = false }).catch((error) => { isPreparingPdf = false; shareError = error.message || 'PDF gagal disiapkan.' })"
                        >
                            <button
                                type="button"
                                :disabled="!isPdfReady || isSharing"
                                @click="isSharing = true; shareError = ''; window.shareItemRequestPdf(@js($pdfUrl), @js($submittedRequest->code), @js($shareMessage)).catch((error) => { if (error.name !== 'AbortError') shareError = error.message || 'PDF gagal dibagikan.' }).finally(() => isSharing = false)"
                                class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-2xs transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:cursor-wait disabled:opacity-70"
                            >
                                <span x-show="isPreparingPdf">Menyiapkan PDF...</span>
                                <span x-cloak x-show="isPdfReady && !isSharing">Bagikan ke WhatsApp</span>
                                <span x-cloak x-show="isSharing">Membuka...</span>
                            </button>
                            <p x-cloak x-show="shareError" x-text="shareError" class="mt-1 text-[11px] text-red-600" role="alert"></p>
                        </div>
                    @endif

                    <button
                        type="button"
                        wire:click="createAnother"
                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300"
                    >
                        <svg class="size-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Buat permintaan baru
                    </button>

                    <a
                        href="{{ url('/') }}"
                        class="inline-flex items-center justify-center gap-1.5 rounded-lg px-3.5 py-2 text-xs font-semibold text-slate-500 transition hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-300"
                    >
                        Kembali ke beranda
                    </a>
                </div>
            </div>
        </div>
    @else
        <form wire:submit="submit" class="space-y-4">
            <!-- Requester Identity -->
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs sm:p-5">
                <h2 class="mb-3 text-sm font-semibold text-slate-900">Data pemohon</h2>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="requester_name" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                            Nama pemohon <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="requester_name"
                            type="text"
                            wire:model="requester_name"
                            autocomplete="name"
                            placeholder="Nama lengkap"
                            class="block w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-900 shadow-2xs outline-none transition placeholder:text-slate-400 focus:border-slate-800 focus:ring-2 focus:ring-slate-200 @error('requester_name') border-red-300 ring-2 ring-red-100 @else border-slate-300 @enderror"
                        >
                        @error('requester_name')
                            <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="division_id" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                            Divisi <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <select
                                id="division_id"
                                wire:model="division_id"
                                class="block w-full appearance-none rounded-lg border bg-white px-3 py-2 pr-8 text-sm text-slate-900 shadow-2xs outline-none transition focus:border-slate-800 focus:ring-2 focus:ring-slate-200 @error('division_id') border-red-300 ring-2 ring-red-100 @else border-slate-300 @enderror"
                            >
                                <option value="">Pilih divisi</option>
                                @foreach ($divisions as $division)
                                    <option value="{{ $division->getKey() }}">{{ $division->name }}</option>
                                @endforeach
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-400">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>
                        </div>
                        @error('division_id')
                            <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="note" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                            Catatan <span class="font-normal normal-case text-slate-400">(opsional)</span>
                        </label>
                        <textarea
                            id="note"
                            wire:model="note"
                            rows="2"
                            placeholder="Keterangan tambahan untuk tim gudang"
                            class="block w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-900 shadow-2xs outline-none transition placeholder:text-slate-400 focus:border-slate-800 focus:ring-2 focus:ring-slate-200 @error('note') border-red-300 ring-2 ring-red-100 @else border-slate-300 @enderror"
                        ></textarea>
                        @error('note')
                            <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </section>

            <!-- Requested Items -->
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs sm:p-5">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-semibold text-slate-900">Barang diminta</h2>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">{{ count($lines) }}</span>
                    </div>

                    <button
                        type="button"
                        wire:click="addLine"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-300"
                    >
                        <svg class="size-3.5 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Tambah barang
                    </button>
                </div>

                @error('lines')
                    <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700">
                        {{ $message }}
                    </div>
                @enderror

                <div class="space-y-3">
                    @foreach ($lines as $index => $line)
                        @php($unit = $items->firstWhere('id', $line['item_id'])?->baseUom?->code)

                        <div
                            wire:key="request-line-{{ $line['key'] }}"
                            class="rounded-lg border border-slate-200 bg-slate-50/60 p-3"
                        >
                            <div class="flex items-start gap-2">
                                <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-md bg-slate-900 text-[10px] font-bold text-white">
                                    {{ $index + 1 }}
                                </span>

                                <div class="min-w-0 flex-1 space-y-2">
                                    <!-- Item -->
                                    <div class="relative">
                                        <label for="item-{{ $line['key'] }}" class="sr-only">Barang</label>

                                        @if (filled($line['item_id']))
                                            @php($selectedItem = $items->firstWhere('id', $line['item_id']))

                                            <div class="flex items-center justify-between gap-2 rounded-lg border border-emerald-200 bg-emerald-50/70 px-3 py-2">
                                                <div class="flex min-w-0 items-center gap-2">
                                                    <svg class="size-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                    </svg>
                                                    <p class="truncate text-sm font-medium text-slate-900">
                                                        {{ $selectedItem?->item_name ?? 'Barang terpilih' }}
                                                    </p>
                                                    <span class="hidden shrink-0 rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[10px] text-slate-500 sm:inline-block">
                                                        {{ $selectedItem?->item_code }}
                                                    </span>
                                                </div>

                                                <button
                                                    type="button"
                                                    wire:click="clearSelectedItem({{ $index }})"
                                                    class="shrink-0 rounded-md px-2 py-1 text-[11px] font-semibold text-slate-500 transition hover:bg-white hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-300"
                                                    title="Ganti barang"
                                                >
                                                    Ganti
                                                </button>
                                            </div>
                                        @else
                                            <div class="relative">
                                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400">
                                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                                    </svg>
                                                </div>
                                                <input
                                                    id="item-{{ $line['key'] }}"
                                                    type="text"
                                                    wire:model.live.debounce.300ms="lines.{{ $index }}.item_query"
                                                    autocomplete="off"
                                                    placeholder="Cari nama atau kode barang"
                                                    class="block w-full rounded-lg border bg-white py-2 pl-8 pr-3 text-sm text-slate-900 shadow-2xs outline-none transition placeholder:text-slate-400 focus:border-slate-800 focus:ring-2 focus:ring-slate-200 @error("lines.$index.item_query") border-red-300 ring-2 ring-red-100 @else border-slate-300 @enderror"
                                                >
                                            </div>

                                            @if (filled($line['item_query']))
                                                @if (($searchResults[$index] ?? collect())->isNotEmpty())
                                                    <div class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                                                        <p class="px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                                                            Katalog ({{ count($searchResults[$index]) }})
                                                        </p>
                                                        <ul class="divide-y divide-slate-100">
                                                            @foreach ($searchResults[$index] as $result)
                                                                <li>
                                                                    <button
                                                                        type="button"
                                                                        wire:key="result-{{ $line['key'] }}-{{ $result->getKey() }}"
                                                                        wire:click="selectItem({{ $index }}, '{{ $result->getKey() }}')"
                                                                        class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left transition hover:bg-slate-50"
                                                                    >
                                                                        <span class="min-w-0">
                                                                            <span class="block truncate text-sm font-medium text-slate-900">{{ $result->item_name }}</span>
                                                                            <span class="block text-[11px] text-slate-500">{{ $result->item_code }}</span>
                                                                        </span>
                                                                        <span class="shrink-0 font-mono text-[11px] text-slate-500">{{ $result->baseUom?->code ?? '—' }}</span>
                                                                    </button>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @else
                                                    <p class="mt-1 text-[11px] text-amber-700">
                                                        Tidak ada di katalog — disimpan sebagai barang manual.
                                                    </p>
                                                @endif
                                            @endif
                                        @endif
                                    </div>

                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-[8.5rem_1fr]">
                                        <!-- Quantity -->
                                        <div class="relative">
                                            <label for="quantity-{{ $line['key'] }}" class="sr-only">Jumlah</label>
                                            <input
                                                id="quantity-{{ $line['key'] }}"
                                                type="number"
                                                step="0.001"
                                                min="0.001"
                                                inputmode="decimal"
                                                wire:model="lines.{{ $index }}.quantity"
                                                placeholder="Jumlah"
                                                class="block w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-900 shadow-2xs outline-none transition placeholder:text-slate-400 focus:border-slate-800 focus:ring-2 focus:ring-slate-200 @error("lines.$index.quantity") border-red-300 ring-2 ring-red-100 @else border-slate-300 @enderror {{ $unit ? 'pr-12' : '' }}"
                                            >
                                            @if ($unit)
                                                <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-[11px] font-semibold uppercase text-slate-400">
                                                    {{ $unit }}
                                                </span>
                                            @endif
                                        </div>

                                        <!-- Line Note -->
                                        <div>
                                            <label for="line-note-{{ $line['key'] }}" class="sr-only">Catatan barang</label>
                                            <input
                                                id="line-note-{{ $line['key'] }}"
                                                type="text"
                                                wire:model="lines.{{ $index }}.note"
                                                placeholder="Catatan (opsional)"
                                                class="block w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-900 shadow-2xs outline-none transition placeholder:text-slate-400 focus:border-slate-800 focus:ring-2 focus:ring-slate-200 @error("lines.$index.note") border-red-300 ring-2 ring-red-100 @else border-slate-300 @enderror"
                                            >
                                        </div>
                                    </div>

                                    @error("lines.$index.item_query")
                                        <p class="text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                    @error("lines.$index.item_id")
                                        <p class="text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                    @error("lines.$index.quantity")
                                        <p class="text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                    @error("lines.$index.note")
                                        <p class="text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <button
                                    type="button"
                                    wire:click="removeLine({{ $index }})"
                                    class="shrink-0 rounded-md p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-300"
                                    title="Hapus barang"
                                >
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                    <span class="sr-only">Hapus barang</span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <!-- Anti-Spam Honeypot Field -->
            <div class="hidden" aria-hidden="true">
                <label for="website">Website</label>
                <input id="website" type="text" wire:model="website" tabindex="-1" autocomplete="off">
            </div>

            <!-- Submit -->
            <div class="flex justify-end">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                >
                    <span wire:loading.remove wire:target="submit" class="flex items-center gap-2">
                        Kirim permintaan
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                        </svg>
                    </span>

                    <span wire:loading wire:target="submit" class="flex items-center gap-2">
                        <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Mengirim...
                    </span>
                </button>
            </div>
        </form>
    @endif
</div>
