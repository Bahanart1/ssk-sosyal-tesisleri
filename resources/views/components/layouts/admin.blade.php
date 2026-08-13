<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Admin Paneli' }} · SSK Sosyal Tesisleri</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="page-canvas min-h-screen" x-data="{ mobileNavOpen: false }">
    <x-toast />

    @php
        $nav = [
            ['route' => 'admin.dashboard', 'label' => 'Genel Bakış', 'icon' => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z'],
            ['route' => 'admin.reservations.index', 'label' => 'Başvurular', 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5'],
            ['route' => 'admin.payments.index', 'label' => 'Ödemeler', 'icon' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z'],
            ['route' => 'admin.periods.index', 'label' => 'Devreler', 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5M12 12.75h.008v.008H12v-.008Z'],
            ['route' => 'admin.tariffs.index', 'label' => 'Tarifeler', 'icon' => 'M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
            ['route' => 'admin.facilities.index', 'label' => 'Tesis & Odalar', 'icon' => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21'],
            ['route' => 'admin.customers.index', 'label' => 'Üyeler', 'icon' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z'],
            ['route' => 'admin.settings.index', 'label' => 'Parametreler', 'icon' => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.03 7.03 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.93 6.93 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z'],
        ];
    @endphp

    <div class="lg:flex">
        <aside class="relative hidden lg:fixed lg:inset-y-0 lg:flex lg:w-64 lg:flex-col overflow-hidden bg-navy-900">
            <div class="pointer-events-none absolute inset-0 bg-brand-mesh opacity-70"></div>
            <div class="pointer-events-none absolute inset-0 bg-noise opacity-40 mix-blend-soft-light"></div>

            <div class="relative flex items-center gap-3 px-5 py-6">
                <div class="brand-mark-sm">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.5 1.5 0 0 1 2.122 0l8.954 8.955M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" /></svg>
                </div>
                <div class="text-white">
                    <p class="font-display text-sm font-semibold leading-tight tracking-tight">SSK Sosyal Tesisleri</p>
                    <p class="text-[11px] text-navy-300">Yönetim Paneli</p>
                </div>
            </div>

            <nav class="relative mt-2 flex-1 space-y-1 px-3">
                @foreach ($nav as $item)
                    @php $active = request()->routeIs($item['route'].'*'); @endphp
                    <a href="{{ route($item['route']) }}"
                       class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200
                              {{ $active ? 'bg-white/10 text-white shadow-inset ring-1 ring-white/10' : 'text-navy-200 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5 flex-shrink-0 {{ $active ? 'text-teal-300' : 'text-navy-400 group-hover:text-teal-300' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" /></svg>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="relative border-t border-white/10 px-3 py-4">
                <div class="flex items-center gap-3 rounded-xl bg-white/5 px-3 py-3 ring-1 ring-white/10">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-teal-500/15 text-sm font-semibold text-teal-300 ring-1 ring-teal-400/30">
                        {{ mb_substr(auth()->user()->name, 0, 1) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-white">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-navy-300">Yönetici</p>
                    </div>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button class="rounded-lg p-1.5 text-navy-300 transition hover:bg-white/10 hover:text-white" title="Çıkış Yap" aria-label="Çıkış Yap">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" /></svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <header class="flex items-center justify-between border-b border-white/70 bg-white/80 px-4 py-3 backdrop-blur-xl lg:hidden">
            <button @click="mobileNavOpen = true" class="rounded-lg p-1.5 text-navy-800 hover:bg-navy-50" aria-label="Menüyü aç">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
            </button>
            <span class="font-display text-sm font-semibold text-navy-900">SSK Yönetim</span>
            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-teal-50 text-xs font-semibold text-teal-700 ring-1 ring-teal-200">
                {{ mb_substr(auth()->user()->name, 0, 1) }}
            </div>
        </header>

        <div x-show="mobileNavOpen" x-cloak class="fixed inset-0 z-40 lg:hidden">
            <div class="modal-scrim" @click="mobileNavOpen = false"></div>
            <div class="absolute inset-y-0 left-0 w-72 overflow-hidden bg-navy-900 px-3 py-5 shadow-lift"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="-translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="-translate-x-full">
                <div class="pointer-events-none absolute inset-0 bg-brand-mesh opacity-60"></div>
                <div class="relative flex items-center justify-between px-3 pb-5">
                    <span class="font-display text-sm font-semibold text-white">SSK Sosyal Tesisleri</span>
                    <button @click="mobileNavOpen = false" class="rounded-lg p-1 text-navy-300 hover:text-white" aria-label="Menüyü kapat">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <nav class="relative space-y-1">
                    @foreach ($nav as $item)
                        <a href="{{ route($item['route']) }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium {{ request()->routeIs($item['route'].'*') ? 'bg-white/10 text-white' : 'text-navy-200' }}">
                            <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" /></svg>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
            </div>
        </div>

        <div class="flex-1 lg:pl-64">
            <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 animate-slide-up">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
