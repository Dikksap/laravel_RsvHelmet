<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0f172a">

        <title>{{ isset($title) ? $title . ' — ' . config('app.name', 'RSV Helmet') : config('app.name', 'RSV Helmet') }}</title>

        <link rel="icon" type="image/x-icon" href="/favicon.ico">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-full bg-slate-100/80 text-slate-900 antialiased font-sans selection:bg-red-600 selection:text-white flex flex-col">
        <!-- Top Navigation Bar -->
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

                <div class="flex items-center gap-3">
                    <div class="hidden items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50/80 px-3 py-1 text-xs font-medium text-emerald-700 sm:inline-flex">
                        <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Sistem Aktif
                    </div>

                    <a
                        href="{{ url('/admin') }}"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-400"
                        title="Masuk ke Panel Admin Gudang"
                    >
                        <svg class="size-3.5 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                        <span>Panel Admin</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="mx-auto w-full max-w-4xl flex-1 px-4 py-8 sm:px-6 lg:py-10">
            {{ $slot }}
        </main>

        <!-- Footer -->
        <footer class="mt-auto border-t border-slate-200/80 bg-white py-6 text-center text-xs text-slate-500">
            <div class="mx-auto max-w-5xl px-4 sm:px-6">
                <p class="font-medium text-slate-600">
                    &copy; {{ date('Y') }} RSV Helmet Indonesia &bull; Sistem Pengelolaan Persediaan & Permintaan Barang
                </p>
                <p class="mt-1 text-slate-400">
                    Formulir internal resmi divisi operasional &bull; Pastikan nomor tiket tersimpan setelah pengajuan
                </p>
            </div>
        </footer>

        @livewireScripts
    </body>
</html>