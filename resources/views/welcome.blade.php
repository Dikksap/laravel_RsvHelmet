@php
    $series = [
        [
            'name' => 'Windbreaker Series',
            'tag' => 'Half Face',
            'description' => 'Helm half face dengan single visor, siluet ramping, dan sertifikasi SNI untuk pemakaian harian.',
            'image' => 'https://rsvhelmets.co.id/cdn/shop/files/rsv-v4-windbreaker.webp?v=1169412267113836894&width=640',
            'url' => 'https://rsvhelmets.co.id/collections/windbreaker-series-grand-launching',
        ],
        [
            'name' => 'New Windtail',
            'tag' => 'Half Face',
            'description' => 'Generasi windtail dengan bentuk belakang aerodinamis dan pilihan finishing glossy maupun carbon.',
            'image' => 'https://rsvhelmets.co.id/cdn/shop/files/rsv-v4-new-windtail.webp?v=15751540982321423084&width=640',
            'url' => 'https://rsvhelmets.co.id/collections/new-windtail',
        ],
        [
            'name' => 'SV300',
            'tag' => 'Double Visor',
            'description' => 'Half face double visor dengan visor dalam terintegrasi, nyaman untuk perjalanan siang dan malam.',
            'image' => 'https://rsvhelmets.co.id/cdn/shop/files/rsv-v4-sv300.webp?v=1564033201576871132&width=640',
            'url' => 'https://rsvhelmets.co.id/collections/sv300',
        ],
        [
            'name' => 'SC Hijab Series',
            'tag' => 'Hijab Friendly',
            'description' => 'Ruang dalam lebih lega dan lapisan lembut, dirancang agar tetap nyaman dipakai bersama hijab.',
            'image' => 'https://rsvhelmets.co.id/cdn/shop/files/rsv-v4-schijab.webp?v=15554915883706198111&width=640',
            'url' => 'https://rsvhelmets.co.id/collections/sc-hijab-series',
        ],
        [
            'name' => 'FFS21',
            'tag' => 'Full Face',
            'description' => 'Helm full face dengan proteksi menyeluruh pada dagu dan wajah untuk berkendara jarak jauh.',
            'image' => 'https://rsvhelmets.co.id/cdn/shop/files/rsv-v4-ffs21.webp?v=1726203194697424816&width=640',
            'url' => 'https://rsvhelmets.co.id/collections/rsv-ffs21-series',
        ],
        [
            'name' => 'Helmet Parts & Accessories',
            'tag' => 'Spare Part',
            'description' => 'Visor, kaca, busa, dan perlengkapan pendukung untuk merawat helm RSV tetap dalam kondisi terbaik.',
            'image' => 'https://rsvhelmets.co.id/cdn/shop/files/rsv-v4-accessories.webp?v=6762999770181801180&width=640',
            'url' => 'https://rsvhelmets.co.id/collections/all',
        ],
    ];

    $highlights = [
        ['value' => '2016', 'label' => 'Berdiri di Bandung'],
        ['value' => 'SNI', 'label' => 'Standar keamanan nasional'],
        ['value' => '360°', 'label' => 'Produksi sampai purna jual'],
        ['value' => 'Nasional', 'label' => 'Jaringan distributor'],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0f172a">
        <meta name="description" content="RSV Helmet oleh PT Helmindo Pratama Indonesia — helm berkualitas dengan sertifikasi SNI, dirancang dan diproduksi di Bandung sejak 2016.">

        <title>{{ config('app.name', 'RSV Helmet') }} — Helm Berkualitas dari Bandung</title>

        <link rel="icon" type="image/x-icon" href="/favicon.ico">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full bg-slate-100/80 text-slate-900 antialiased font-sans selection:bg-red-600 selection:text-white flex flex-col">
        <!-- Top Navigation -->
        <header class="sticky top-0 z-30 w-full border-b border-slate-200/80 bg-white/95 backdrop-blur-md shadow-xs">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <a href="{{ url('/') }}" class="group flex items-center gap-3 transition">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-2.5 py-1 text-sm font-black tracking-tight text-white shadow-xs transition group-hover:bg-slate-800">
                            RSV
                            <span class="ml-0.5 size-1.5 rounded-full bg-red-600"></span>
                        </span>
                        <span class="text-sm font-extrabold uppercase tracking-widest text-slate-800">
                            Helmet
                        </span>
                    </div>
                    <span class="hidden h-4 w-px bg-slate-300 lg:inline-block"></span>
                    <span class="hidden text-xs font-semibold uppercase tracking-wider text-slate-500 lg:inline-block">
                        PT Helmindo Pratama Indonesia
                    </span>
                </a>

                <nav class="hidden items-center gap-6 text-xs font-semibold uppercase tracking-wider text-slate-500 md:flex">
                    <a href="#series" class="transition hover:text-slate-900">Series</a>
                    <a href="#tentang" class="transition hover:text-slate-900">Tentang</a>
                    <a href="#kontak" class="transition hover:text-slate-900">Kontak</a>
                </nav>

                <div class="flex items-center gap-2">
                    <a
                        href="{{ route('item-requests.create') }}"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-2xs transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        <svg class="size-4 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                        <span class="hidden sm:inline">Permintaan Barang</span>
                    </a>
                    <a
                        href="{{ url('/admin') }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3.5 py-2 text-xs font-bold text-white shadow-2xs transition hover:bg-slate-800"
                    >
                        <svg class="size-4 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                        <span class="hidden sm:inline">Portal Admin</span>
                    </a>
                </div>
            </div>
        </header>

        <main class="flex-1">
            <!-- Hero -->
            <section class="relative isolate overflow-hidden bg-slate-950">
                <img
                    src="https://rsvhelmets.co.id/cdn/shop/files/banner_BOB_v2.jpg?v=1794815148&width=1920"
                    alt="Kampanye RSV Helmet"
                    class="absolute inset-0 size-full object-cover object-center opacity-45"
                    loading="eager"
                >
                <div class="absolute inset-0 bg-gradient-to-b from-slate-950/85 via-slate-950/70 to-slate-950/95"></div>

                <div class="relative mx-auto grid w-full max-w-6xl items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:py-28 lg:px-8">
                    <div class="space-y-6">
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3.5 py-1 text-xs font-medium text-slate-100 backdrop-blur-sm">
                            <span class="size-2 rounded-full bg-red-500"></span>
                            Bandung, Indonesia &middot; Sejak 2016
                        </span>

                        <h1 class="text-4xl font-black leading-tight tracking-tight text-white sm:text-5xl lg:text-6xl">
                            Helm yang aman,
                            <span class="block text-red-500">gaya yang berbicara.</span>
                        </h1>

                        <p class="max-w-xl text-base leading-relaxed text-slate-300">
                            RSV Helmet merancang dan memproduksi helm di Bandung dengan standar SNI.
                            Kami percaya helm bukan sekadar alat keselamatan, tetapi bagian dari gaya
                            berkendara sehari-hari — dari desain hingga layanan purna jual.
                        </p>

                        <div class="flex flex-col gap-3 pt-2 sm:flex-row">
                            <a
                                href="https://rsvhelmets.co.id/collections/all"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center justify-center gap-2.5 rounded-xl bg-red-600 px-6 py-3.5 text-sm font-bold text-white shadow-sm transition hover:bg-red-500"
                            >
                                <span>Lihat Katalog Resmi</span>
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                            <a
                                href="#series"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/20 bg-white/5 px-6 py-3.5 text-sm font-bold text-white backdrop-blur-sm transition hover:bg-white/10"
                            >
                                Jelajahi Series
                            </a>
                        </div>
                    </div>

                    <div class="relative hidden lg:block">
                        <div class="absolute -inset-6 rounded-[2rem] bg-red-600/20 blur-3xl"></div>
                        <img
                            src="https://rsvhelmets.co.id/cdn/shop/files/rsv-v4-windbreaker-red.webp?v=3554100748384744443&width=1200"
                            alt="Helm RSV Windbreaker Red Line"
                            class="relative mx-auto w-full max-w-md drop-shadow-2xl"
                            loading="eager"
                        >
                    </div>
                </div>
            </section>

            <!-- Highlights -->
            <section class="border-b border-slate-200 bg-white">
                <div class="mx-auto grid w-full max-w-6xl grid-cols-2 gap-px overflow-hidden px-4 py-8 sm:px-6 lg:grid-cols-4 lg:px-8">
                    @foreach ($highlights as $highlight)
                        <div class="px-2 py-3 text-center lg:text-left">
                            <p class="text-2xl font-black tracking-tight text-slate-900">{{ $highlight['value'] }}</p>
                            <p class="mt-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $highlight['label'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <!-- Series -->
            <section id="series" class="scroll-mt-20 bg-slate-100/80">
                <div class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
                    <div class="max-w-2xl">
                        <span class="text-xs font-bold uppercase tracking-widest text-red-600">Lini Produk</span>
                        <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">
                            Enam keluarga produk RSV
                        </h2>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600">
                            Setiap series dikembangkan untuk kebutuhan berkendara yang berbeda —
                            dari perjalanan dalam kota sampai touring jarak jauh.
                        </p>
                    </div>

                    <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($series as $item)
                            <a
                                href="{{ $item['url'] }}"
                                target="_blank"
                                rel="noopener"
                                class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xs transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md"
                            >
                                <div class="relative flex aspect-[4/3] items-center justify-center bg-slate-50 p-6">
                                    <img
                                        src="{{ $item['image'] }}"
                                        alt="Helm RSV {{ $item['name'] }}"
                                        class="max-h-full w-auto object-contain transition duration-300 group-hover:scale-105"
                                        loading="lazy"
                                    >
                                    <span class="absolute left-3 top-3 rounded-md bg-slate-900/90 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-white">
                                        {{ $item['tag'] }}
                                    </span>
                                </div>

                                <div class="flex flex-1 flex-col gap-2 border-t border-slate-100 p-5">
                                    <div class="flex items-start justify-between gap-2">
                                        <h3 class="text-sm font-extrabold tracking-tight text-slate-900">{{ $item['name'] }}</h3>
                                        <svg class="mt-0.5 size-4 shrink-0 text-slate-400 transition group-hover:text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M17 7H8m9 0v9" />
                                        </svg>
                                    </div>
                                    <p class="text-xs leading-relaxed text-slate-600">{{ $item['description'] }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>

            <!-- Tentang -->
            <section id="tentang" class="scroll-mt-20 bg-white">
                <div class="mx-auto grid w-full max-w-6xl items-center gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8 lg:py-20">
                    <div class="relative order-2 lg:order-1">
                        <div class="overflow-hidden rounded-2xl border border-slate-200 shadow-sm">
                            <img
                                src="https://cdn.shopify.com/s/files/1/0460/2082/1159/files/banner_schijab_Potrait.jpg?v=1780762723&width=1000"
                                alt="Koleksi RSV SC Hijab Series"
                                class="h-full w-full object-cover"
                                loading="lazy"
                            >
                        </div>
                        <div class="absolute -bottom-5 -right-3 hidden rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-md sm:block">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Didirikan oleh</p>
                            <p class="text-sm font-extrabold text-slate-900">Ilham Pratama</p>
                        </div>
                    </div>

                    <div class="order-1 space-y-5 lg:order-2">
                        <span class="text-xs font-bold uppercase tracking-widest text-red-600">Tentang Kami</span>
                        <h2 class="text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">
                            Merek helm Indonesia dengan standar kelas dunia
                        </h2>
                        <p class="text-sm leading-relaxed text-slate-600">
                            RSV Helmet lahir di Bandung pada 2016 dan dikelola oleh
                            PT Helmindo Pratama Indonesia. Visi kami sederhana: menjadi merek helm
                            kelas dunia yang bernilai tinggi, dengan produk yang aman, nyaman, dan
                            tetap relevan secara gaya.
                        </p>
                        <p class="text-sm leading-relaxed text-slate-600">
                            Untuk mewujudkannya kami menjalankan strategi 360 derajat — mulai dari
                            riset dan produksi, distribusi nasional, sampai layanan purna jual dan
                            ketersediaan suku cadang.
                        </p>

                        <ul class="grid gap-3 pt-2 sm:grid-cols-2">
                            <li class="flex items-start gap-2.5 rounded-lg border border-slate-200 bg-slate-50/70 p-3">
                                <span class="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-600/10 text-emerald-700">
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </span>
                                <span class="text-xs font-semibold text-slate-700">Tersertifikasi SNI</span>
                            </li>
                            <li class="flex items-start gap-2.5 rounded-lg border border-slate-200 bg-slate-50/70 p-3">
                                <span class="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-600/10 text-emerald-700">
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </span>
                                <span class="text-xs font-semibold text-slate-700">Produksi dalam negeri</span>
                            </li>
                            <li class="flex items-start gap-2.5 rounded-lg border border-slate-200 bg-slate-50/70 p-3">
                                <span class="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-600/10 text-emerald-700">
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </span>
                                <span class="text-xs font-semibold text-slate-700">Distributor resmi nasional</span>
                            </li>
                            <li class="flex items-start gap-2.5 rounded-lg border border-slate-200 bg-slate-50/70 p-3">
                                <span class="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-600/10 text-emerald-700">
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </span>
                                <span class="text-xs font-semibold text-slate-700">Suku cadang tersedia</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- Portal Internal -->
            <section class="bg-slate-950">
                <div class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
                    <div class="max-w-2xl">
                        <span class="text-xs font-bold uppercase tracking-widest text-red-500">Akses Internal</span>
                        <h2 class="mt-2 text-3xl font-black tracking-tight text-white sm:text-4xl">
                            Portal persediaan &amp; produksi
                        </h2>
                        <p class="mt-3 text-sm leading-relaxed text-slate-400">
                            Area kerja untuk tim gudang, produksi, dan divisi lain. Ajukan permintaan
                            barang secara mandiri atau masuk ke panel admin untuk mengelola stok,
                            bill of materials, dan batch produksi.
                        </p>
                    </div>

                    <div class="mt-8 grid gap-4 md:grid-cols-2">
                        <a
                            href="{{ route('item-requests.create') }}"
                            class="group flex items-start justify-between gap-4 rounded-2xl border border-white/10 bg-white/5 p-6 transition hover:border-red-500/40 hover:bg-white/10"
                        >
                            <div class="space-y-2">
                                <span class="inline-flex size-10 items-center justify-center rounded-xl bg-red-600/15 text-red-500">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                    </svg>
                                </span>
                                <h3 class="text-base font-extrabold text-white">Formulir Permintaan Barang</h3>
                                <p class="text-xs leading-relaxed text-slate-400">
                                    Isi kebutuhan material atau perlengkapan divisi, lalu pantau status persetujuannya.
                                </p>
                            </div>
                            <svg class="mt-1 size-5 shrink-0 text-slate-500 transition group-hover:text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </a>

                        <a
                            href="{{ url('/admin') }}"
                            class="group flex items-start justify-between gap-4 rounded-2xl border border-white/10 bg-white/5 p-6 transition hover:border-red-500/40 hover:bg-white/10"
                        >
                            <div class="space-y-2">
                                <span class="inline-flex size-10 items-center justify-center rounded-xl bg-red-600/15 text-red-500">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                                    </svg>
                                </span>
                                <h3 class="text-base font-extrabold text-white">Panel Admin</h3>
                                <p class="text-xs leading-relaxed text-slate-400">
                                    Stok per gudang, stock opname, BOM, dan realisasi batch produksi dalam satu dasbor.
                                </p>
                            </div>
                            <svg class="mt-1 size-5 shrink-0 text-slate-500 transition group-hover:text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </a>
                    </div>
                </div>
            </section>

            <!-- Kontak -->
            <section id="kontak" class="scroll-mt-20 bg-white">
                <div class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
                    <div class="grid gap-10 lg:grid-cols-2 lg:gap-16">
                        <div class="space-y-5">
                            <span class="text-xs font-bold uppercase tracking-widest text-red-600">Hubungi Kami</span>
                            <h2 class="text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">
                                Kantor &amp; layanan pelanggan
                            </h2>
                            <p class="text-sm leading-relaxed text-slate-600">
                                Untuk pembelian, kerja sama distributor, atau pertanyaan produk,
                                hubungi tim RSV Helmet melalui kanal resmi berikut.
                            </p>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                                <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-900 text-white">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                    </svg>
                                </span>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Kantor Pusat</p>
                                    <p class="mt-0.5 text-sm font-semibold leading-relaxed text-slate-800">
                                        Jl. Cikutra No. 205, Neglasari, Kec. Cibeunying Kaler,<br>
                                        Kota Bandung, Jawa Barat 40124
                                    </p>
                                </div>
                            </div>

                            <a
                                href="https://wa.me/6282114145566"
                                target="_blank"
                                rel="noopener"
                                class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4 transition hover:border-emerald-300 hover:bg-emerald-50/50"
                            >
                                <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-600 text-white">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                                    </svg>
                                </span>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">WhatsApp</p>
                                    <p class="mt-0.5 text-sm font-semibold text-slate-800">+62 821-1414-5566</p>
                                </div>
                            </a>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <a
                                    href="https://www.instagram.com/rsvhelmets"
                                    target="_blank"
                                    rel="noopener"
                                    class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4 transition hover:border-slate-300 hover:bg-white"
                                >
                                    <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-900 text-white">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <rect x="3" y="3" width="18" height="18" rx="5" />
                                            <circle cx="12" cy="12" r="3.75" />
                                            <circle cx="17.25" cy="6.75" r="1" fill="currentColor" stroke="none" />
                                        </svg>
                                    </span>
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Instagram</p>
                                        <p class="mt-0.5 text-sm font-semibold text-slate-800">@rsvhelmets</p>
                                    </div>
                                </a>

                                <a
                                    href="https://rsvhelmets.co.id/pages/contact-us"
                                    target="_blank"
                                    rel="noopener"
                                    class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4 transition hover:border-slate-300 hover:bg-white"
                                >
                                    <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-900 text-white">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                        </svg>
                                    </span>
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Halaman Kontak</p>
                                        <p class="mt-0.5 text-sm font-semibold text-slate-800">rsvhelmets.co.id</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200 bg-slate-100/80 py-8">
            <div class="mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-2.5 py-1 text-xs font-black tracking-tight text-white">
                        RSV<span class="ml-0.5 size-1.5 rounded-full bg-red-600"></span>
                    </span>
                    <div>
                        <p class="text-xs font-bold text-slate-800">PT Helmindo Pratama Indonesia</p>
                        <p class="text-[11px] text-slate-500">&copy; {{ date('Y') }} RSV Helmet. Seluruh hak dilindungi.</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    <a href="https://rsvhelmets.co.id/" target="_blank" rel="noopener" class="transition hover:text-slate-900">Toko Resmi</a>
                    <a href="https://rsvhelmets.co.id/pages/career" target="_blank" rel="noopener" class="transition hover:text-slate-900">Karier</a>
                    <a href="{{ route('item-requests.create') }}" class="transition hover:text-slate-900">Permintaan Barang</a>
                    <a href="{{ url('/admin') }}" class="transition hover:text-slate-900">Panel Admin</a>
                </div>
            </div>
        </footer>
    </body>
</html>
