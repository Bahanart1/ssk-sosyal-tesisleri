<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Giriş' }} · SSK Sosyal Tesisleri</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-navy-950">
    <div class="min-h-screen lg:grid lg:grid-cols-[1.05fr_0.95fr]">
        <!-- Marka paneli -->
        <aside class="relative flex min-h-[38vh] flex-col justify-between overflow-hidden bg-navy-900 px-6 py-8 sm:px-10 sm:py-10 lg:min-h-screen lg:px-14 lg:py-12">
            <div class="pointer-events-none absolute inset-0 bg-brand-mesh"></div>
            <div class="pointer-events-none absolute inset-0 opacity-[0.35] bg-noise mix-blend-soft-light"></div>
            <div class="pointer-events-none absolute -right-24 top-1/3 h-72 w-72 rounded-full bg-teal-400/10 blur-3xl"></div>
            <div class="pointer-events-none absolute -left-16 bottom-10 h-56 w-56 rounded-full bg-navy-400/20 blur-3xl"></div>

            <div class="relative flex items-center gap-3 animate-fade-in">
                <div class="brand-mark">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.5 1.5 0 0 1 2.122 0l8.954 8.955M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" /></svg>
                </div>
                <div class="text-white">
                    <p class="font-display text-lg font-semibold tracking-tight text-white">SSK Sosyal Tesisleri</p>
                    <p class="text-xs text-white/70">Rezervasyon Yönetim Sistemi</p>
                </div>
            </div>

            <div class="relative mt-10 max-w-lg animate-slide-up lg:mt-0">
                <p class="section-label !text-teal-300">Kurumsal rezervasyon</p>
                <h1 class="mt-3 font-display text-3xl font-semibold leading-[1.15] tracking-tight text-white sm:text-4xl lg:text-[2.75rem]">
                    Güvenilir ve şeffaf tesis rezervasyonu.
                </h1>
                <p class="mt-4 max-w-md text-sm leading-relaxed text-white/75 sm:text-base">
                    Sosyal tesislerimizde yer ayırtın, onay sürecini takip edin ve ödemenizi güvenle tamamlayın.
                </p>
                <div class="mt-8 hidden gap-6 border-t border-white/15 pt-6 text-sm text-white/65 lg:flex">
                    <div>
                        <p class="font-display text-2xl font-semibold text-white">7/24</p>
                        <p class="mt-1 text-xs">Talep oluşturma</p>
                    </div>
                    <div>
                        <p class="font-display text-2xl font-semibold text-white">Anlık</p>
                        <p class="mt-1 text-xs">Durum takibi</p>
                    </div>
                    <div>
                        <p class="font-display text-2xl font-semibold text-white">Güvenli</p>
                        <p class="mt-1 text-xs">Ödeme adımı</p>
                    </div>
                </div>
            </div>

            <p class="relative mt-8 hidden text-xs text-navy-300/80 lg:block">&copy; {{ date('Y') }} SSK Sosyal Tesisleri. Tüm hakları saklıdır.</p>
        </aside>

        <!-- Form paneli -->
        <main class="relative flex items-center justify-center bg-sand-50 px-5 py-10 sm:px-10 lg:px-14">
            <div class="pointer-events-none absolute inset-0 opacity-60" style="background-image:radial-gradient(circle at 1px 1px, rgba(10,23,40,0.05) 1px, transparent 0); background-size: 24px 24px;"></div>
            <div class="relative w-full max-w-[26rem] animate-rise">
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
