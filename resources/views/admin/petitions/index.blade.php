<x-layouts.admin title="Dilekçeler">

    <div class="mb-6">
        <p class="section-label">Yönetim</p>
        <h1 class="page-title mt-1">Dilekçeler</h1>
        <p class="page-subtitle">Üyelerden gelen talep, itiraz ve bildirimler.</p>
    </div>

    {{-- Durum sekmeleri --}}
    @php
        $tabs = ['' => 'Tümü', 'open' => 'Yanıt bekleyen', 'answered' => 'Yanıtlanan', 'closed' => 'Kapatılan'];
    @endphp
    <div class="mb-5 flex flex-wrap gap-2">
        @foreach ($tabs as $value => $label)
            @php $active = (string) request('status') === (string) $value; @endphp
            <a href="{{ route('admin.petitions.index', array_filter(['status' => $value ?: null] + request()->except(['status', 'page']))) }}"
               class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium transition-all {{ $active ? 'bg-accent-600 text-white' : 'bg-surface text-ink ring-1 ring-line hover:bg-surface-alt' }}">
                {{ $label }}
                @if ($value && isset($counts[$value]))
                    <span class="rounded-md px-1.5 py-0.5 text-[10px] {{ $active ? 'bg-white/15' : 'bg-surface-sunken' }}">{{ $counts[$value] }}</span>
                @endif
            </a>
        @endforeach
    </div>

    <form method="GET" class="surface mb-6 p-4">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <div class="grid gap-x-4 gap-y-3">
            <div class="sm:col-span-2 lg:col-span-2">
                <label class="field-label">Ara</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Üye adı, TC veya üyelik no" class="field-input">
            </div>
        </div>
        <div class="mt-4 flex items-center justify-between gap-3 border-t border-line pt-3">
            <p class="text-xs text-ink-muted"><strong class="tabular-nums text-ink">{{ $petitions->total() }}</strong> dilekçe</p>
            <button type="submit" class="btn-primary !px-4 !py-1.5 text-xs">Filtrele</button>
        </div>
    </form>

    <div class="space-y-4">
        @forelse ($petitions as $petition)
            <div x-data="{ acik: {{ $petition->isOpen() ? 'true' : 'false' }} }" class="surface overflow-hidden">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4">
                    <div class="min-w-0">
                        <h2 class="font-semibold text-ink">{{ $petition->subject ?: 'Dilekçe' }}</h2>
                        <p class="mt-0.5 text-xs text-ink-muted">
                            <a href="{{ route('admin.customers.show', $petition->user) }}" class="font-medium hover:underline">{{ $petition->user->name }}</a>
                            · {{ $petition->user->membership_no ?? $petition->user->maskedTcNo() }}
                            · {{ $petition->created_at->translatedFormat('d F Y H:i') }}
                            @if ($petition->reservation)
                                · <a href="{{ route('admin.reservations.show', $petition->reservation) }}" class="font-mono hover:underline">{{ $petition->reservation->code }}</a>
                            @endif
                        </p>
                    </div>
                    <span class="badge-{{ $petition->isOpen() ? 'amber' : ($petition->status === 'answered' ? 'green' : 'gray') }}">
                        {{ $petition->statusLabel() }}
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-3 px-5 py-4">
                    @if ($petition->attachment_path)
                        <a href="{{ route('documents.petition', $petition) }}" target="_blank" rel="noopener"
                           class="btn-primary !px-4 !py-2 text-xs">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                            Dilekçeyi görüntüle
                        </a>
                    @else
                        <span class="text-xs text-ink-subtle">Dosya eklenmemiş (eski kayıt).</span>
                    @endif

                    {{-- Eski kayıtlardaki metinler okunmaya devam eder --}}
                    @if ($petition->body)
                        <p class="w-full whitespace-pre-line text-sm leading-relaxed text-ink">{{ $petition->body }}</p>
                    @endif
                </div>

                @if ($petition->reply)
                    <div class="border-t border-line bg-surface-alt px-5 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">
                            Yanıt · {{ $petition->responder?->name }} · {{ $petition->replied_at?->translatedFormat('d F Y H:i') }}
                        </p>
                        <p class="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-ink">{{ $petition->reply }}</p>
                    </div>
                @endif

                <div class="border-t border-line px-5 py-4">
                    <button type="button" @click="acik = !acik" x-show="!acik" class="btn-secondary !px-3 !py-1.5 text-xs">
                        {{ $petition->reply ? 'Yanıtı güncelle' : 'Yanıtla' }}
                    </button>

                    <form x-show="acik" x-cloak method="POST" action="{{ route('admin.petitions.reply', $petition) }}" class="space-y-3">
                        @csrf
                        <textarea name="reply" rows="4" class="field-input" placeholder="Üyeye iletilecek yanıt">{{ old('reply', $petition->reply) }}</textarea>
                        <div class="flex flex-wrap items-center gap-3">
                            <select name="status" class="field-input !w-auto !py-1.5 text-xs">
                                <option value="answered">Yanıtlandı</option>
                                <option value="closed">Kapat</option>
                            </select>
                            <button type="submit" class="btn-primary !px-4 !py-1.5 text-xs">Gönder</button>
                            <button type="button" @click="acik = false" class="btn-ghost !px-3 !py-1.5 text-xs">Vazgeç</button>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <div class="surface empty-state !py-16"><p class="text-sm text-ink-subtle">Bu filtreye uyan dilekçe yok.</p></div>
        @endforelse
    </div>

    <div class="mt-6">{{ $petitions->links() }}</div>
</x-layouts.admin>
