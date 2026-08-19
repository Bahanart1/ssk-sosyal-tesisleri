<x-layouts.customer title="Ana Sayfa">

    {{-- ---------------------------------------------------------------- --}}
    {{-- Karşılama — yaklaşan konaklama varsa onu, yoksa daveti gösterir  --}}
    {{-- ---------------------------------------------------------------- --}}
    @if ($upcoming)
        @php
            $kalanGun = (int) now()->startOfDay()->diffInDays($upcoming->start_date, false);
        @endphp
        <section class="relative mb-8 overflow-hidden rounded-3xl">
            <img src="{{ $upcoming->facility->coverUrl() }}" alt="{{ $upcoming->facility->name }}"
                 class="h-[22rem] w-full object-cover sm:h-[26rem]" loading="eager">
            <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/40 to-black/10"></div>

            <div class="absolute inset-x-0 bottom-0 p-6 sm:p-8">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/70">
                    @if ($kalanGun > 0)
                        Tatilinize {{ $kalanGun }} gün kaldı
                    @elseif ($kalanGun === 0)
                        Tatiliniz bugün başlıyor
                    @else
                        Konaklamanız sürüyor
                    @endif
                </p>
                <h1 class="mt-2 font-display text-3xl font-semibold text-white sm:text-4xl">
                    {{ $upcoming->facility->name }}
                </h1>
                <p class="mt-1 text-sm text-white/80">{{ $upcoming->facility->location }}</p>

                <div class="mt-5 flex flex-wrap items-center gap-x-8 gap-y-3">
                    <div>
                        <p class="text-[11px] uppercase tracking-wider text-white/60">Giriş</p>
                        <p class="text-sm font-medium text-white">{{ $upcoming->start_date->translatedFormat('d F Y') }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] uppercase tracking-wider text-white/60">Çıkış</p>
                        <p class="text-sm font-medium text-white">{{ $upcoming->end_date->translatedFormat('d F Y') }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] uppercase tracking-wider text-white/60">Oda</p>
                        <p class="text-sm font-medium text-white">
                            {{ $upcoming->roomType->name }}@if ($upcoming->room) · {{ $upcoming->room->label() }}@endif
                        </p>
                    </div>
                    <a href="{{ route('customer.reservations.show', $upcoming) }}"
                       class="ml-auto rounded-xl bg-white/95 px-4 py-2 text-sm font-semibold text-slate-900 backdrop-blur transition hover:bg-white">
                        Rezervasyon detayı
                    </a>
                </div>
            </div>
        </section>
    @else
        <section class="relative mb-8 overflow-hidden rounded-3xl">
            <img src="{{ $facilities->first()?->coverUrl() }}" alt=""
                 class="h-72 w-full object-cover sm:h-80" loading="eager">
            <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/45 to-black/15"></div>

            <div class="absolute inset-x-0 bottom-0 p-6 sm:p-8">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-white/70">Hoş geldiniz, {{ Str::of($user->name)->explode(' ')->first() }}</p>
                <h1 class="mt-2 max-w-xl font-display text-3xl font-semibold leading-tight text-white sm:text-4xl">
                    Deniz kenarında ya da Kaz Dağları eteklerinde bir hafta.
                </h1>
                <p class="mt-2 max-w-lg text-sm text-white/80">
                    Devrenizi seçin, kişilerinizi bildirin, peşinatınızı ödeyin. Gerisini Dernek hallediyor.
                </p>

                @if ($canApply)
                    <a href="{{ route('customer.reservations.create') }}"
                       class="mt-5 inline-flex items-center gap-2 rounded-xl bg-white px-5 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-white/90">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        Rezervasyon yap
                    </a>
                @endif
            </div>
        </section>
    @endif

    {{-- Başvuru engelleri --}}
    @if ($hasDuesDebt)
        <div class="alert-soft mb-6 border-amber-200 bg-amber-50 text-amber-900 ring-amber-200 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-100 dark:ring-amber-800">
            <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
            <div class="min-w-0 flex-1">
                <p class="font-semibold">Aidat borcunuz var — <x-money :value="$duesDebtTotal" /></p>
                <p class="mt-1 text-sm">
                    Borç ödenmediği sürece rezervasyon talebiniz işleme alınmaz.
                    Borçlu yıllar: {{ $outstandingDues->pluck('year')->implode(', ') }}.
                </p>
                <a href="{{ route('customer.dues.index') }}" class="mt-2 inline-flex text-sm font-semibold underline">Aidat detayları →</a>
            </div>
        </div>
    @elseif (! $user->customer_group_id)
        <div class="alert-soft mb-6 border-amber-200 bg-amber-50 text-amber-900 ring-amber-200 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-100 dark:ring-amber-800">
            <p>Hesabınıza henüz bir müşteri grubu atanmamış. Rezervasyon yapabilmek için Dernek ile iletişime geçin.</p>
        </div>
    @endif

    {{-- Ödeme bekleyenler --}}
    @foreach ($awaitingPayment as $reservation)
        <div class="surface mb-4 overflow-hidden border-accent-300 dark:border-accent-700">
            <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <img src="{{ $reservation->facility->coverUrl() }}" alt=""
                         class="hidden h-14 w-20 rounded-xl object-cover sm:block" loading="lazy">
                    <div>
                        <p class="section-label">Yeriniz ayrıldı · ödeme bekleniyor</p>
                        <p class="mt-1 text-lg font-semibold text-ink">
                            {{ $reservation->facility->name }} · {{ $reservation->period->label() }}
                        </p>
                        <p class="mt-0.5 text-sm text-ink-muted">
                            Kalan <x-money :value="$reservation->balanceDue()" class="font-semibold text-ink" />
                        </p>
                    </div>
                </div>
                <a href="{{ route('customer.payment.show', $reservation) }}" class="btn-accent shrink-0">Ödemeyi tamamla</a>
            </div>
        </div>
    @endforeach

    {{-- ---------------------------------------------------------------- --}}
    {{-- Tesisler                                                          --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="mb-8">
        <div class="mb-4 flex items-end justify-between gap-4">
            <div>
                <h2 class="font-display text-xl font-semibold text-ink">Tesislerimiz</h2>
                <p class="text-sm text-ink-muted">İki tesis, mayıstan ekime kadar haftalık devreler.</p>
            </div>
            @if ($canApply)
                <a href="{{ route('customer.reservations.create') }}" class="btn-accent shrink-0 !px-4 !py-2 text-sm">Rezervasyon yap</a>
            @endif
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            @foreach ($facilities as $facility)
                <article class="group overflow-hidden rounded-2xl bg-surface ring-1 ring-line transition hover:ring-accent-400">
                    <div class="relative overflow-hidden">
                        <img src="{{ $facility->coverUrl() }}" alt="{{ $facility->name }}"
                             class="h-52 w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
                        @if ($facility->open_periods_count > 0)
                            <span class="absolute left-3 top-3 rounded-full bg-black/60 px-2.5 py-1 text-[11px] font-medium text-white backdrop-blur">
                                {{ $facility->open_periods_count }} devre açık
                            </span>
                        @endif
                    </div>

                    <div class="p-5">
                        <h3 class="font-display text-lg font-semibold text-ink">{{ $facility->name }}</h3>
                        <p class="text-xs text-ink-muted">{{ $facility->location }}</p>
                        <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-ink-muted">{{ $facility->description }}</p>

                        {{-- Küçük galeri --}}
                        <div class="mt-4 grid grid-cols-4 gap-1.5">
                            @foreach (array_slice($facility->galleryUrls(), 1, 4) as $gorsel)
                                <img src="{{ $gorsel }}" alt="" class="h-14 w-full rounded-lg object-cover" loading="lazy">
                            @endforeach
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Rezervasyonlarım + aidat özeti                                    --}}
    {{-- ---------------------------------------------------------------- --}}
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="surface overflow-hidden lg:col-span-2">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h2 class="text-base font-semibold text-ink">Rezervasyonlarım</h2>
                @if ($total > 0)
                    <a href="{{ route('customer.reservations.index') }}" class="text-xs font-semibold text-accent-600 hover:text-accent-700 dark:text-accent-400">Tümü →</a>
                @endif
            </div>

            @if ($recent->isEmpty())
                <div class="empty-state">
                    <svg class="h-10 w-10 text-ink-subtle" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                    <p class="font-medium text-ink-muted">Henüz bir rezervasyonunuz yok.</p>
                    @if ($canApply)
                        <a href="{{ route('customer.reservations.create') }}" class="btn-primary mt-2">İlk rezervasyonunuzu yapın</a>
                    @endif
                </div>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($recent as $reservation)
                        <li>
                            <a href="{{ route('customer.reservations.show', $reservation) }}"
                               class="flex items-center gap-4 px-5 py-4 transition-colors hover:bg-surface-alt">
                                <img src="{{ $reservation->facility->coverUrl() }}" alt=""
                                     class="h-12 w-16 shrink-0 rounded-lg object-cover" loading="lazy">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-medium text-ink">{{ $reservation->facility->name }}</p>
                                        <x-status-badge :status="$reservation->status" :label="$reservation->collectsOnSite() ? 'Tesiste Ödeyecek' : null" />
                                    </div>
                                    <p class="mt-0.5 text-xs text-ink-muted">
                                        {{ $reservation->roomType->name }} ·
                                        {{ $reservation->start_date->translatedFormat('d M') }} – {{ $reservation->end_date->translatedFormat('d M Y') }}
                                    </p>
                                </div>
                                <x-money :value="$reservation->total_price" class="shrink-0 font-semibold text-ink" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Yan sütun --}}
        <div class="space-y-4">
            <a href="{{ route('customer.dues.index') }}" class="surface surface-hover block p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-ink-subtle">Aidat durumu</p>
                @if (! $user->isMember())
                    <p class="mt-2 text-2xl font-semibold text-ink">Muaf</p>
                    <p class="mt-1 text-xs text-ink-muted">Dernek üyesi olmayanlar aidat ödemez</p>
                @else
                    <p class="mt-2 text-2xl font-semibold {{ $hasDuesDebt ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                        {{ $hasDuesDebt ? 'Borçlu' : 'Güncel' }}
                    </p>
                    <p class="mt-1 text-xs text-ink-muted">
                        {{ $hasDuesDebt ? '₺' . number_format($duesDebtTotal, 0, ',', '.') . ' ödenmedi' : 'Borcunuz bulunmuyor' }}
                    </p>
                @endif
            </a>

            @if ($balanceTotal > 0)
                <div class="surface p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-ink-subtle">Bekleyen ödeme</p>
                    <p class="mt-2 text-2xl font-semibold text-ink">₺{{ number_format($balanceTotal, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-ink-muted">{{ $awaitingPayment->count() }} rezervasyon için</p>
                </div>
            @endif

            <div class="surface p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-ink-subtle">Üyeliğiniz</p>
                <p class="mt-2 font-medium text-ink">{{ $user->name }}</p>
                <p class="mt-0.5 text-xs text-ink-muted">
                    {{ $user->customerGroup?->name ?? 'Grup atanmadı' }}
                    @if ($user->membership_no) · {{ $user->membership_no }} @endif
                </p>
                <a href="{{ route('customer.profile.edit') }}" class="mt-3 inline-flex text-xs font-semibold text-accent-600 hover:text-accent-700 dark:text-accent-400">
                    Bilgilerimi düzenle →
                </a>
            </div>
        </div>
    </div>
</x-layouts.customer>
