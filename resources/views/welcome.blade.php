<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'RSV Helmet') }}</title>

        <link rel="icon" type="image/x-icon" href="/favicon.ico">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full bg-slate-100/80 text-slate-900 antialiased font-sans selection:bg-red-600 selection:text-white flex flex-col">
        <header class="sticky top-0 z-30 w-full border-b border-slate-200/80 bg-white/95 backdrop-blur-md shadow-xs">
            <div class="mx-auto flex h-16 max-w-5xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="{{ url('/') }}" class="group flex items-center gap-3 transition">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-2.5 py-1 text-sm font-black tracking-tight text-white shadow-xs group-hover:bg-slate-800 transition">
                            RSV
                            <span class="ml-0.5 size-1.5 rounded-full bg-red-600"></span>
                        </span>
                        <span class="text-sm font-extrabold tracking-widest uppercase text-slate-800">
                            HELMET
                        </span>
                    </div>
                    <span class="hidden h-4 w-px bg-slate-300 sm:inline-block"></span>
                    <span class="hidden text-xs font-semibold uppercase tracking-wider text-slate-500 sm:inline-block">
                        Logistik & Gudang
                    </span>
                </a>

                <nav class="flex items-center gap-3">
                    <a
                        href="{{ route('item-requests.create') }}"
                        class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3.5 py-2 text-xs font-bold text-white shadow-2xs transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900"
                    >
                        <svg class="size-4 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                        <span>Permintaan Barang</span>
                    </a>

                    <a
                        href="{{ url('/admin') }}"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50"
                    >
                        <span>Panel Admin</span>
                    </a>
                </nav>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-5xl flex-1 items-center justify-center px-4 py-16 sm:px-6">
            <div class="w-full max-w-2xl text-center space-y-6">
                <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3.5 py-1 text-xs font-medium text-slate-600 shadow-2xs">
                    <span class="size-2 rounded-full bg-emerald-500"></span>
                    Sistem Manajemen Gudang & Logistik
                </span>

                <h1 class="text-3xl font-black tracking-tight text-slate-900 sm:text-5xl">
                    Portal Internal Persediaan RSV Helmet
                </h1>

                <p class="text-base text-slate-600 leading-relaxed max-w-xl mx-auto">
                    Kelola kebutuhan material, suku cadang, dan perlengkapan produksi. Ajukan formulir permintaan barang secara mandiri antar divisi kerja.
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
                    <a
                        href="{{ route('item-requests.create') }}"
                        class="inline-flex w-full sm:w-auto items-center justify-center gap-2.5 rounded-xl bg-slate-900 px-6 py-3.5 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900"
                    >
                        <span>Buat Permintaan Barang</span>
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </a>

                    <a
                        href="{{ url('/admin') }}"
                        class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-6 py-3.5 text-sm font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50"
                    >
                        <span>Login Admin Gudang</span>
                    </a>
                </div>
            </div>
        </main>

        <footer class="border-t border-slate-200/80 bg-white py-6 text-center text-xs text-slate-500">
            <div class="mx-auto max-w-5xl px-4 sm:px-6">
                <p class="font-medium text-slate-600">
                    &copy; {{ date('Y') }} RSV Helmet Indonesia &bull; Warehouse & Production Management System
                </p>
            </div>
        </footer>
    </body>
</html>