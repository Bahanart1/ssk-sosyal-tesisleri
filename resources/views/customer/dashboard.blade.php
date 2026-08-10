<x-layouts.customer title="Panelim">

    <div class="mb-8 flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div>
            <p class="section-label">Müşteri paneli</p>
            <h1 class="page-title mt-1">Merhaba, {{ explode(' ', $user->name)[0] }}</h1>
            <p class="page-subtitle">
                Müşteri sınıfınız:
                <span class="font-semibold text-navy-800">{{ $user->customerClass?->name ?? 'Atanmadı' }}</span>
                @if ($user->customerClass)
                    <span class="text-slate-400">·</span>
                    Günlük <span class="font-semibold text-teal-700">₺{{ number_format($user->customerClass->daily_price, 0, ',', '.') }}</span>
                    <span class="text-slate-400">·</span>
                    Haftalık kamp <span class="font-semibold text-navy-800">₺{{ number_format($user->customerClass->daily_price * 7, 0, ',', '.') }}</span>
                @endif
            </p>
        </div>
        <a href="{{ route('customer.reservations.create') }}" class="btn-accent shrink-0 self-start sm:self-auto">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            Yeni Rezervasyon
        </a>
    </div>

    @if ($current || $upcoming)
        @php $highlight = $current ?? $upcoming; @endphp
        <section class="surface mb-8 overflow-hidden animate-rise">
            <div class="relative overflow-hidden bg-navy-900 px-6 py-5">
                <div class="pointer-events-none absolute inset-0 bg-brand-mesh opacity-80"></div>
                <div class="relative flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="section-label !text-teal-300">
                            {{ $current ? 'Aktif rezervasyonunuz' : 'Yaklaşan rezervasyonunuz' }}
                        </p>
                        <h2 class="mt-1 font-display text-xl font-semibold tracking-tight text-white sm:text-2xl">{{ $highlight->facility->name }}</h2>
                    </div>
                    <x-status-badge :status="$highlight->status" class="!bg-white/10 !text-white !ring-white/20" />
                </div>
            </div>
            <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-1">
                    <p class="text-sm text-slate-500">
                        {{ $highlight->check_in->translatedFormat('d M Y') }}
                        <span class="text-slate-300">→</span>
                        {{ $highlight->check_out->translatedFormat('d M Y') }}
                    </p>
                    <p class="text-sm font-medium text-navy-800">1 haftalık kamp · {{ $highlight->nights() }} gece · ₺{{ number_format($highlight->total_price, 0, ',', '.') }}</p>
                </div>
                <a href="{{ route('customer.reservations.show', $highlight) }}" class="btn-secondary">Detayı Gör</a>
            </div>
        </section>
    @else
        <section class="surface empty-state mb-8 animate-rise">
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-50 to-navy-50 ring-1 ring-teal-100">
                <svg class="h-7 w-7 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
            </div>
            <div>
                <p class="font-display text-xl font-semibold text-navy-900">Henüz rezervasyonunuz yok</p>
            <p class="max-w-sm text-sm text-slate-500">Sosyal tesislerimizde 1 haftalık kamp dönemi ayırtmak için yeni bir talep oluşturabilirsiniz.</p>
            </div>
            <a href="{{ route('customer.reservations.create') }}" class="btn-accent mt-1">Rezervasyon Oluştur</a>
        </section>
    @endif

    <section class="surface overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100/80 px-6 py-4">
            <h2 class="font-display text-lg font-semibold text-navy-900">Rezervasyon geçmişi</h2>
            <span class="text-xs font-medium text-slate-400">{{ $reservations->count() }} kayıt</span>
        </div>

        @if ($reservations->isEmpty())
            <p class="px-6 py-12 text-center text-sm text-slate-400">Kayıtlı rezervasyon bulunamadı.</p>
        @else
            <ul class="divide-y divide-slate-100/80">
                @foreach ($reservations as $r)
                    <li>
                        <a href="{{ route('customer.reservations.show', $r) }}" class="group flex flex-col gap-3 px-6 py-4 transition-colors hover:bg-teal-50/40 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-semibold text-navy-900 transition-colors group-hover:text-teal-800">{{ $r->facility->name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $r->check_in->translatedFormat('d M Y') }} — {{ $r->check_out->translatedFormat('d M Y') }}
                                    <span class="text-slate-300">·</span>
                                    ₺{{ number_format($r->total_price, 0, ',', '.') }}
                                </p>
                            </div>
                            <x-status-badge :status="$r->status" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.customer>
