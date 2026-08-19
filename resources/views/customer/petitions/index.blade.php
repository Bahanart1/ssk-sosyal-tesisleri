<x-layouts.customer title="Dilekçelerim">

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="section-label">İletişim</p>
            <h1 class="page-title mt-1">Dilekçelerim</h1>
            <p class="page-subtitle">Dilekçenizi yazıp fotoğrafını veya PDF'ini yükleyin; Dernek yanıtı burada görünür.</p>
        </div>
        <img src="{{ asset('images/tesisler/gure-005.webp') }}" alt=""
             class="hidden h-20 w-36 rounded-xl object-cover sm:block" loading="lazy">
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        {{-- Yeni dilekçe: yalnızca dosya --}}
        <div class="surface overflow-hidden self-start lg:col-span-2">
            <div class="border-b border-line px-5 py-4">
                <h2 class="text-base font-semibold text-ink">Dilekçe gönder</h2>
                <p class="text-xs text-ink-muted">Islak imzalı dilekçenizin fotoğrafı veya PDF'i yeterlidir.</p>
            </div>

            <form method="POST" action="{{ route('customer.petitions.store') }}" enctype="multipart/form-data" class="space-y-4 p-5">
                @csrf

                <div>
                    <label for="attachment" class="field-label">Dilekçe dosyası</label>
                    <input id="attachment" type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf" required
                           class="field-input !py-2 file:mr-3 file:rounded-lg file:border-0 file:bg-accent-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                    <p class="field-hint">JPG, PNG veya PDF · en fazla 5 MB. Ad, soyad ve talebinizin dilekçede yazılı olduğundan emin olun.</p>
                    @error('attachment') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="btn-accent w-full">Dilekçeyi gönder</button>
            </form>
        </div>

        {{-- Geçmiş --}}
        <div class="space-y-4 lg:col-span-3">
            @forelse ($petitions as $petition)
                <div class="surface overflow-hidden">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-ink">
                                {{ $petition->subject ?: 'Dilekçe' }}
                            </h3>
                            <p class="mt-0.5 text-xs text-ink-muted">
                                {{ $petition->created_at->translatedFormat('d F Y H:i') }}
                            </p>
                        </div>
                        <span class="badge-{{ $petition->isOpen() ? 'amber' : ($petition->status === 'answered' ? 'green' : 'gray') }}">
                            {{ $petition->statusLabel() }}
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 px-5 py-4">
                        @if ($petition->attachment_path)
                            <a href="{{ route('documents.petition', $petition) }}" target="_blank" rel="noopener"
                               class="btn-secondary !px-3 !py-1.5 text-xs">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12H9m6.75 3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                Dilekçemi görüntüle
                            </a>
                        @endif
                        {{-- Eski kayıtlarda metin varsa okunmaya devam eder --}}
                        @if ($petition->body)
                            <p class="w-full whitespace-pre-line text-sm leading-relaxed text-ink">{{ $petition->body }}</p>
                        @endif
                    </div>

                    @if ($petition->reply)
                        <div class="border-t border-line bg-surface-alt px-5 py-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Dernek yanıtı</p>
                            <p class="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-ink">{{ $petition->reply }}</p>
                            <p class="mt-2 text-[11px] text-ink-subtle">
                                {{ $petition->replied_at?->translatedFormat('d F Y H:i') }}
                            </p>
                        </div>
                    @endif
                </div>
            @empty
                <div class="surface empty-state !py-16">
                    <svg class="h-10 w-10 text-ink-subtle" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                    <p class="font-medium text-ink-muted">Henüz dilekçeniz yok.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-layouts.customer>
