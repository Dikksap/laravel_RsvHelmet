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
            <div class="mx-auto flex h-14 max-w-5xl items-center px-4 sm:px-6 lg:px-8">
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
            </div>
        </header>

        <!-- Main Content -->
        <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-6 sm:px-6 lg:py-8">
            {{ $slot }}
        </main>

        <!-- Footer -->
        <footer class="mt-auto border-t border-slate-200/80 bg-white py-4 text-center text-[11px] text-slate-400">
            &copy; {{ date('Y') }} RSV Helmet Indonesia
        </footer>

        @livewireScripts
    </body>
</html>