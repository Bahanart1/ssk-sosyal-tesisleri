@props(['photo' => null, 'eyebrow' => 'Kurumsal rezervasyon', 'heading' => null, 'lede' => null])

<!DOCTYPE html>
<html lang="tr">
<head>
    @include('partials.head', ['title' => $title ?? 'Giriş'])
</head>
<body class="min-h-screen bg-canvas">
    <div class="min-h-screen lg:grid lg:grid-cols-[1fr_1fr]">
        {{--
            Sol panel. Üye girişinde tesis fotoğrafı üzerinde durur (tatil hissi);
            yönetici girişinde düz kurumsal zemin kalır.
        --}}
        <aside class="relative flex min-h-[38vh] flex-col justify-between overflow-hidden px-6 py-8 sm:px-10 lg:min-h-screen lg:px-14 lg:py-12 {{ $photo ? '' : 'bg-chrome' }}">
            @if ($photo)
                <img src="{{ $photo }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="eager">
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/95 via-slate-950/70 to-slate-950/45"></div>
            @endif

            <div class="relative flex items-center gap-3">
                <x-brand-logo class="h-9 w-auto text-chrome-ink" />
            </div>

            <div class="relative mt-10 max-w-lg lg:mt-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-accent-300">{{ $eyebrow }}</p>
                <h1 class="mt-3 font-display text-2xl font-semibold leading-snug tracking-tight text-chrome-ink sm:text-3xl">
                    {{ $heading ?? 'Çolaklı ve Güre tesisleri için müracaat, değerlendirme ve ödeme tek sistemde.' }}
                </h1>
                <p class="mt-4 max-w-md text-sm leading-relaxed text-chrome-muted">
                    {{ $lede ?? 'Devrenizi seçin, kişilerinizi bildirin, peşinatınızı ödeyin; yer tahsisi ve bakiye ödemesini panelinizden takip edin.' }}
                </p>

                {{ $aside ?? '' }}
            </div>

            <p class="relative mt-8 hidden text-xs text-chrome-muted lg:block">
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
