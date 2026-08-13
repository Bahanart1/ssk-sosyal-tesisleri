<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Müşteri Paneli' }} · SSK Sosyal Tesisleri</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="page-canvas min-h-screen">
    <x-toast />

    <header class="sticky top-0 z-30 border-b border-white/60 bg-white/75 backdrop-blur-xl">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3.5 sm:px-6">
            <a href="{{ route('customer.dashboard') }}" class="group flex items-center gap-3">
                <div class="brand-mark-sm transition-transform duration-200 group-hover:scale-105">
                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.5 1.5 0 0 1 2.122 0l8.954 8.955M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" /></svg>
                </div>
                <div class="leading-tight">
                    <p class="font-display text-base font-semibold tracking-tight text-navy-900">SSK Sosyal Tesisleri</p>
                    <p class="hidden text-[11px] text-stone-500 sm:block">Müşteri paneli</p>
                </div>
            </a>

            <div class="flex items-center gap-3 sm:gap-4">
                <div class="hidden text-right sm:block">
                    <p class="text-sm font-semibold text-navy-900">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-stone-500">{{ auth()->user()->customerGroup?->name ?? 'Grup atanmadı' }}</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-teal-50 to-teal-100 text-sm font-bold text-teal-800 ring-1 ring-teal-200/80">
                    {{ mb_substr(auth()->user()->name, 0, 1) }}
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn-ghost !rounded-full !px-2.5 !py-2.5" title="Çıkış Yap" aria-label="Çıkış Yap">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" /></svg>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-10 animate-slide-up">
        {{ $slot }}
    </main>
</body>
</html>
