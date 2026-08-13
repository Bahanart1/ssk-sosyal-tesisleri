<!DOCTYPE html>
<html lang="tr">
<head>
    @include('partials.head', ['title' => $title ?? 'Giriş'])
</head>
<body class="min-h-screen bg-canvas">
    <div class="min-h-screen lg:grid lg:grid-cols-[1fr_1fr]">
        {{-- Kurumsal panel --}}
        <aside class="relative flex min-h-[32vh] flex-col justify-between bg-chrome px-6 py-8 sm:px-10 lg:min-h-screen lg:px-14 lg:py-12">
            <div class="flex items-center gap-3">
                <div class="brand-mark">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.5 1.5 0 0 1 2.122 0l8.954 8.955M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" /></svg>
                </div>
                <div>
                    <p class="text-base font-semibold tracking-tight text-chrome-ink">SSK Sosyal Tesisleri</p>
                    <p class="text-xs text-chrome-muted">Rezervasyon Yönetim Sistemi</p>
                </div>
            </div>

            <div class="mt-10 max-w-lg lg:mt-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-accent-300">Kurumsal rezervasyon</p>
                <h1 class="mt-3 text-2xl font-semibold leading-snug tracking-tight text-chrome-ink sm:text-3xl">
                    Çolaklı ve Güre tesisleri için müracaat, değerlendirme ve ödeme tek sistemde.
                </h1>
                <p class="mt-4 max-w-md text-sm leading-relaxed text-chrome-muted">
                    Devrenizi seçin, kişilerinizi bildirin, peşinatınızı ödeyin; yer tahsisi ve
                    bakiye ödemesini panelinizden takip edin.
                </p>

                <dl class="mt-8 hidden gap-8 border-t border-white/10 pt-6 lg:flex">
                    <div>
                        <dt class="text-xs text-chrome-muted">Tesis</dt>
                        <dd class="mt-1 text-xl font-semibold text-chrome-ink">2</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-chrome-muted">Devre süresi</dt>
                        <dd class="mt-1 text-xl font-semibold text-chrome-ink">6 gün</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-chrome-muted">Ödeme</dt>
                        <dd class="mt-1 text-xl font-semibold text-chrome-ink">3D Secure</dd>
                    </div>
                </dl>
            </div>

            <p class="mt-8 hidden text-xs text-chrome-muted lg:block">
                &copy; {{ date('Y') }} Sigorta Eğitim, Dinlenme ve Sosyal Tesisler Derneği
            </p>
        </aside>

        {{-- Form paneli --}}
        <main class="relative flex items-center justify-center px-5 py-10 sm:px-10 lg:px-14">
            <div class="absolute right-4 top-4">
                @include('partials.theme-toggle')
            </div>
            <div class="w-full max-w-sm">
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
