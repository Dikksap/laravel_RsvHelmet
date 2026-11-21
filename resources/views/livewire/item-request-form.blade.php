<div class="space-y-6">
    <!-- Top Hero Header -->
    <header class="relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs sm:p-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1.5">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-700">
                        <span class="size-1.5 rounded-full bg-red-600 animate-pulse"></span>
                        RSV Helmet Portal
                    </span>
                    <span class="text-xs font-medium text-slate-400">&bull;</span>
                    <span class="text-xs font-medium text-slate-500">Divisi Operasional & Produksi</span>
                </div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                    Formulir Permintaan Barang
                </h1>
                <p class="text-sm leading-relaxed text-slate-600 sm:max-w-2xl">
                    Isi data pemohon dan daftar barang yang dibutuhkan. Satu formulir dapat meminta lebih dari satu barang. Permintaan Anda akan langsung diteruskan ke tim gudang untuk diproses.
                </p>
            </div>

            <!-- Quick Step Indicators -->
            <div class="hidden shrink-0 rounded-xl border border-slate-100 bg-slate-50/80 p-3 text-xs text-slate-500 lg:block">
                <p class="font-semibold text-slate-700 mb-2">Panduan Pengajuan:</p>
                <ol class="space-y-1.5 list-decimal list-inside">
                    <li>Lengkapi identitas pemohon & divisi</li>
                    <li>Pilih barang atau tulis nama manual</li>
                    <li>Kirim formulir & simpan nomor tiket</li>
                </ol>
            </div>
        </div>
    </header>

    @if ($submitted)
        <!-- Success Confirmation View -->
        <div class="overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm">
            <!-- Header Banner -->
            <div class="bg-gradient-to-r from-emerald-600 to-teal-600 p-6 text-white sm:p-8">
                <div class="flex items-center gap-4">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-sm text-white shadow-inner">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </span>
                    <div>
                        <span class="inline-block rounded-md bg-white/20 px-2 py-0.5 text-xs font-semibold uppercase tracking-wider text-emerald-100">
                            Pengajuan Berhasil
                        </span>
                        <h2 class="mt-1 text-xl font-bold tracking-tight text-white sm:text-2xl">
                            Permintaan berhasil dikirim
                        </h2>
                    </div>
                </div>
            </div>

            <!-- Ticket Details Card -->
            <div class="p-6 sm:p-8 space-y-6">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-5 sm:p-6 text-center sm:text-left">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Nomor Tiket Permintaan Resmi
                            </p>
                            <p class="mt-1 font-mono text-2xl sm:text-3xl font-extrabold tracking-wider text-slate-900">
                                {{ $submittedCode }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                Tanggal: {{ now()->translatedFormat('d F Y, H:i') }} WIB &bull; Status: <span class="font-semibold text-amber-600">Menunggu Diproses</span>
                            </p>
                        </div>

                        <!-- Copy to Clipboard Button (Alpine.js) -->
                        <div x-data="{ copied: false }" class="shrink-0 flex justify-center sm:justify-end">
                            <button
                                type="button"
                                @click="navigator.clipboard.writeText('{{ $submittedCode }}'); copied = true; setTimeout(() => copied = false, 2500)"
                                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-400"
                            >
                                <template x-if="!copied">
                                    <span class="flex items-center gap-1.5">
                                        <svg class="size-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                                        </svg>
                                        Salin Nomor Tiket
                                    </span>
                                </template>
                                <template x-if="copied">
                                    <span class="flex items-center gap-1.5 text-emerald-600 font-bold">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                        Tersalin!
                                    </span>
                                </template>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Next Steps Instruction Guide -->
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <h3 class="text-sm font-semibold text-slate-900">Langkah Selanjutnya:</h3>
                    <ul class="mt-3 space-y-2 text-xs sm:text-sm text-slate-600">
                        <li class="flex items-start gap-2.5">
                            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-slate-100 font-bold text-slate-700 text-xs">1</span>
                            <span>Simpan atau bagikan nomor permintaan <strong class="font-mono text-slate-900">{{ $submittedCode }}</strong> ke tim gudang atau logistik.</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-slate-100 font-bold text-slate-700 text-xs">2</span>
                            <span>Petugas gudang akan memverifikasi ketersediaan stok fisik barang yang diminta.</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-slate-100 font-bold text-slate-700 text-xs">3</span>
                            <span>Pengambilan barang dapat dilakukan di loket logistik dengan menyebutkan nomor tiket ini.</span>
                        </li>
                    </ul>
                </div>

                <!-- Actions -->
                <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 pt-2">
                    <a
                        href="{{ url('/') }}"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-2xs transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400"
                    >
                        <svg class="size-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                        Kembali ke Beranda
                    </a>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            onclick="window.print()"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-2xs transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400"
                        >
                            <svg class="size-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24-1.258.625-2.429 1.89-2.429h7.78c1.265 0 2.13 1.171 1.89 2.429M6.75 9h10.5M6 18h12a2 2 0 002-2v-4a2 2 0 00-2-2H6a2 2 0 00-2 2v4a2 2 0 002 2zM9 18v2a1 1 0 001 1h4a1 1 0 001-1v-2" />
                            </svg>
                            Cetak Bukti
                        </button>

                        <button
                            type="button"
                            wire:click="createAnother"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                        >
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Buat permintaan baru
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- Main Form -->
        <form wire:submit="submit" class="space-y-6">
            <!-- SECTION 1: DATA PEMOHON -->
            <section class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs sm:p-8 transition-shadow">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4 mb-6">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white font-bold text-sm shadow-xs">
                        1
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Data Pemohon</h2>
                        <p class="text-xs text-slate-500">Lengkapi identitas pemohon dan divisi penanggung jawab</p>
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <!-- Requester Name -->
                    <div>
                        <label for="requester_name" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                            Nama pemohon <span class="text-red-600">*</span>
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                            </div>
                            <input
                                id="requester_name"
                                type="text"
                                wire:model="requester_name"
                                autocomplete="name"
                                placeholder="Nama lengkap pemohon (contoh: Budi Santoso)"
                                class="block w-full rounded-xl border @error('requester_name') border-red-300 ring-2 ring-red-100 text-red-900 @else border-slate-300 text-slate-900 @enderror bg-white pl-10 pr-3.5 py-2.5 text-sm shadow-2xs outline-none transition focus:border-slate-800 focus:ring-2 focus:ring-slate-200"
                            >
                        </div>
                        @error('requester_name')
                            <p class="mt-1.5 flex items-center gap-1 text-xs font-medium text-red-600">
                                <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="12" y1="8" x2="12" y2="12" />
                                    <line x1="12" y1="16" x2="12.01" y2="16" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Division -->
                    <div>
                        <label for="division_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                            Divisi <span class="text-red-600">*</span>
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                </svg>
                            </div>
                            <select
                                id="division_id"
                                wire:model="division_id"
                                class="block w-full appearance-none rounded-xl border @error('division_id') border-red-300 ring-2 ring-red-100 text-red-900 @else border-slate-300 text-slate-900 @enderror bg-white pl-10 pr-10 py-2.5 text-sm shadow-2xs outline-none transition focus:border-slate-800 focus:ring-2 focus:ring-slate-200"
                            >
                                <option value="">Pilih divisi pemohon...</option>
                                @foreach ($divisions as $division)
                                    <option value="{{ $division->getKey() }}">{{ $division->name }}</option>
                                @endforeach
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>
                        </div>
                        @error('division_id')
                            <p class="mt-1.5 flex items-center gap-1 text-xs font-medium text-red-600">
                                <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="12" y1="8" x2="12" y2="12" />
                                    <line x1="12" y1="16" x2="12.01" y2="16" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Note -->
                    <div class="sm:col-span-2">
                        <label for="note" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                            Catatan permintaan <span class="font-normal lowercase text-slate-500">(opsional)</span>
                        </label>
                        <div class="relative">
                            <textarea
                                id="note"
                                wire:model="note"
                                rows="3"
                                placeholder="Jelaskan kebutuhan pengajuan ini (contoh: Kebutuhan material perakitan helm line 2 shift pagi)..."
                                class="block w-full rounded-xl border @error('note') border-red-300 ring-2 ring-red-100 text-red-900 @else border-slate-300 text-slate-900 @enderror bg-white px-3.5 py-2.5 text-sm shadow-2xs outline-none transition focus:border-slate-800 focus:ring-2 focus:ring-slate-200"
                            ></textarea>
                        </div>
                        <div class="mt-1.5 flex items-center justify-between text-xs text-slate-500">
                            <span>Maksimal 1000 karakter. Keterangan ini akan terlihat oleh petugas gudang.</span>
                        </div>
                        @error('note')
                            <p class="mt-1.5 flex items-center gap-1 text-xs font-medium text-red-600">
                                <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="12" y1="8" x2="12" y2="12" />
                                    <line x1="12" y1="16" x2="12.01" y2="16" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>
            </section>

            <!-- SECTION 2: DAFTAR BARANG YANG DIMINTA -->
            <section class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs sm:p-8 transition-shadow">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-4 mb-6">
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white font-bold text-sm shadow-xs">
                            2
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-base font-bold text-slate-900">Barang yang diminta</h2>
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                                    {{ count($lines) }} Item
                                </span>
                            </div>
                            <p class="text-xs text-slate-500">Pilih dari katalog master atau tulis manual jika tidak ditemukan</p>
                        </div>
                    </div>

                    <button
                        type="button"
                        wire:click="addLine"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-800 shadow-2xs transition hover:bg-slate-50 hover:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-300"
                    >
                        <svg class="size-4 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span>Tambah barang</span>
                    </button>
                </div>

                @error('lines')
                    <div class="mb-5 flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 p-3.5 text-sm text-red-700">
                        <svg class="size-5 shrink-0 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="8" x2="12" y2="12" />
                            <line x1="12" y1="16" x2="12.01" y2="16" />
                        </svg>
                        <p class="font-medium">{{ $message }}</p>
                    </div>
                @enderror

                <!-- Lines List -->
                <div class="space-y-4">
                    @foreach ($lines as $index => $line)
                        @php($unit = $items->firstWhere('id', $line['item_id'])?->baseUom?->code)

                        <div
                            wire:key="request-line-{{ $line['key'] }}"
                            class="relative rounded-xl border border-slate-200/90 bg-slate-50/70 p-4 sm:p-5 transition hover:border-slate-300 hover:bg-slate-50/90"
                        >
                            <!-- Line Header: Item Index & Remove Button -->
                            <div class="flex items-center justify-between border-b border-slate-200/60 pb-3 mb-4">
                                <span class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-700">
                                    <span class="flex size-5 items-center justify-center rounded-md bg-slate-200 text-slate-800 text-[11px]">
                                        {{ $index + 1 }}
                                    </span>
                                    Barang #{{ $index + 1 }}
                                </span>

                                <button
                                    type="button"
                                    wire:click="removeLine({{ $index }})"
                                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold text-red-600 transition hover:bg-red-100/70 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-300"
                                    title="Hapus barang"
                                >
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                    <span>Hapus</span>
                                </button>
                            </div>

                            <!-- Fields Grid -->
                            <div class="grid gap-4 sm:grid-cols-12">
                                <!-- Item Search / Selection Column -->
                                <div class="relative sm:col-span-6">
                                    <label for="item-{{ $line['key'] }}" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1">
                                        Barang <span class="text-red-600">*</span>
                                    </label>

                                    @if (filled($line['item_id']))
                                        @php($selectedItem = $items->firstWhere('id', $line['item_id']))

                                        <!-- Selected Item Display Card -->
                                        <div class="flex items-center justify-between gap-3 rounded-xl border border-emerald-300 bg-emerald-50/50 px-3.5 py-2.5 shadow-2xs">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-emerald-600 text-white shadow-2xs">
                                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                    </svg>
                                                </span>
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-bold text-slate-900">
                                                        {{ $selectedItem?->item_name ?? 'Barang terpilih' }}
                                                    </p>
                                                    <p class="text-xs text-slate-500">
                                                        Kode: <span class="font-mono font-medium text-slate-700">{{ $selectedItem?->item_code }}</span>
                                                        &bull; Satuan: <span class="font-semibold text-emerald-800">{{ $selectedItem?->baseUom?->code ?? '—' }}</span>
                                                    </p>
                                                </div>
                                            </div>

                                            <button
                                                type="button"
                                                wire:click="clearSelectedItem({{ $index }})"
                                                class="shrink-0 inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-slate-900"
                                                title="Ganti pilihan barang"
                                            >
                                                <svg class="size-3 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                                </svg>
                                                Ganti
                                            </button>
                                        </div>
                                    @else
                                        <!-- Search / Manual Input -->
                                        <div class="relative">
                                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                                </svg>
                                            </div>
                                            <input
                                                id="item-{{ $line['key'] }}"
                                                type="text"
                                                wire:model.live.debounce.300ms="lines.{{ $index }}.item_query"
                                                autocomplete="off"
                                                placeholder="Ketik nama atau kode barang..."
                                                class="block w-full rounded-xl border @error("lines.$index.item_query") border-red-300 ring-2 ring-red-100 text-red-900 @else border-slate-300 text-slate-900 @enderror bg-white pl-9 pr-3.5 py-2.5 text-sm shadow-2xs outline-none transition focus:border-slate-800 focus:ring-2 focus:ring-slate-200"
                                            >
                                        </div>

                                        @if (filled($line['item_query']))
                                            @if (($searchResults[$index] ?? collect())->isNotEmpty())
                                                <!-- Dropdown Results from Master Catalog -->
                                                <div class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                                                    <div class="border-b border-slate-100 bg-slate-50/80 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                                        Katalog Master ({{ count($searchResults[$index]) }} hasil)
                                                    </div>
                                                    <ul class="divide-y divide-slate-100">
                                                        @foreach ($searchResults[$index] as $result)
                                                            <li>
                                                                <button
                                                                    type="button"
                                                                    wire:key="result-{{ $line['key'] }}-{{ $result->getKey() }}"
                                                                    wire:click="selectItem({{ $index }}, '{{ $result->getKey() }}')"
                                                                    class="flex w-full items-center justify-between gap-3 px-3.5 py-2.5 text-left text-sm transition hover:bg-slate-100"
                                                                >
                                                                    <div class="min-w-0">
                                                                        <p class="truncate font-semibold text-slate-900">{{ $result->item_name }}</p>
                                                                        <p class="text-xs text-slate-500">Kode: {{ $result->item_code }}</p>
                                                                    </div>
                                                                    <span class="shrink-0 rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs font-semibold text-slate-700">
                                                                        {{ $result->baseUom?->code ?? '—' }}
                                                                    </span>
                                                                </button>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                                <p class="mt-1 text-xs text-slate-500">Klik barang untuk memilih, atau biarkan teks untuk menyimpan sebagai barang manual.</p>
                                            @else
                                                <!-- Manual Item Notice -->
                                                <div class="mt-1.5 flex items-start gap-1.5 rounded-lg border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">
                                                    <svg class="size-4 shrink-0 text-amber-600 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                                                    </svg>
                                                    <span>Barang tidak ditemukan. Permintaan akan dicatat manual dengan nama "{{ $line['item_query'] }}".</span>
                                                </div>
                                            @endif
                                        @endif
                                    @endif

                                    @error("lines.$index.item_query")
                                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                    @error("lines.$index.item_id")
                                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Quantity Column -->
                                <div class="sm:col-span-3">
                                    <label for="quantity-{{ $line['key'] }}" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1">
                                        Jumlah <span class="text-red-600">*</span>
                                    </label>
                                    <input
                                        id="quantity-{{ $line['key'] }}"
                                        type="number"
                                        step="0.001"
                                        min="0.001"
                                        wire:model="lines.{{ $index }}.quantity"
                                        placeholder="0"
                                        class="block w-full rounded-xl border @error("lines.$index.quantity") border-red-300 ring-2 ring-red-100 text-red-900 @else border-slate-300 text-slate-900 @enderror bg-white px-3.5 py-2.5 text-sm shadow-2xs outline-none transition focus:border-slate-800 focus:ring-2 focus:ring-slate-200"
                                    >
                                    @error("lines.$index.quantity")
                                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Unit Column -->
                                <div class="sm:col-span-3">
                                    <span class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1">
                                        Satuan
                                    </span>
                                    <div class="flex h-[42px] items-center rounded-xl border border-slate-200 bg-slate-100/80 px-3.5 text-sm font-semibold text-slate-700 shadow-2xs">
                                        {{ $unit ?? '—' }}
                                    </div>
                                </div>

                                <!-- Note for Specific Line -->
                                <div class="sm:col-span-12">
                                    <label for="line-note-{{ $line['key'] }}" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1">
                                        Catatan barang <span class="font-normal lowercase text-slate-500">(opsional)</span>
                                    </label>
                                    <input
                                        id="line-note-{{ $line['key'] }}"
                                        type="text"
                                        wire:model="lines.{{ $index }}.note"
                                        placeholder="Misalnya ukuran (M/L/XL), warna, nomor seri, atau keterangan peruntukan khusus"
                                        class="block w-full rounded-xl border @error("lines.$index.note") border-red-300 ring-2 ring-red-100 text-red-900 @else border-slate-300 text-slate-900 @enderror bg-white px-3.5 py-2 text-sm shadow-2xs outline-none transition focus:border-slate-800 focus:ring-2 focus:ring-slate-200"
                                    >
                                    @error("lines.$index.note")
                                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Add More Item Button at Bottom of List -->
                <div class="mt-4 pt-2">
                    <button
                        type="button"
                        wire:click="addLine"
                        class="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 p-3 text-xs font-semibold text-slate-600 transition hover:border-slate-400 hover:bg-slate-50/80 hover:text-slate-900"
                    >
                        <svg class="size-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span>Tambah barang lainnya</span>
                    </button>
                </div>
            </section>

            <!-- Anti-Spam Honeypot Field -->
            <div class="hidden" aria-hidden="true">
                <label for="website">Website</label>
                <input id="website" type="text" wire:model="website" tabindex="-1" autocomplete="off">
            </div>

            <!-- SUBMIT ACTION BAR -->
            <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <svg class="size-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                        </svg>
                        <span>Pastikan jumlah dan nama barang telah sesuai sebelum menekan tombol kirim.</span>
                    </div>

                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="submit"
                        class="inline-flex items-center justify-center gap-2.5 rounded-xl bg-slate-900 px-6 py-3.5 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <!-- Normal state -->
                        <span wire:loading.remove wire:target="submit" class="flex items-center gap-2">
                            <span>Kirim permintaan</span>
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                            </svg>
                        </span>

                        <!-- Loading state -->
                        <span wire:loading wire:target="submit" class="flex items-center gap-2">
                            <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Mengirim...</span>
                        </span>
                    </button>
                </div>
            </div>
        </form>
    @endif
</div>