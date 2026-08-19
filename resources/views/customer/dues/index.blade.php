<x-layouts.customer title="Aidatlarım">

    <div x-data="{ odenecek: null }">

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="section-label">Üyelik</p>
            <h1 class="page-title mt-1">Aidatlarım</h1>
            <p class="page-subtitle">Aidatınız güncel olduğu sürece tesislerden yararlanabilirsiniz.</p>
        </div>
        <img src="{{ asset('images/tesisler/colakli-6.webp') }}" alt=""
             class="hidden h-20 w-36 rounded-xl object-cover sm:block" loading="lazy">
    </div>

    @if (! $member->isMember())
        {{-- III. Grup: dernek üyesi değil --}}
        <div class="surface p-6">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-ink-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                <div>
                    <p class="font-semibold text-ink">Aidat yükümlülüğünüz bulunmuyor</p>
                    <p class="mt-1 text-sm text-ink-muted">
                        {{ $member->customerGroup?->name ?? 'Grubunuz' }} kapsamındaki misafirler dernek üyesi
                        olmadığından aidat ödemez. Tesislerden yararlanma koşullarınız değişmez.
                    </p>
                </div>
            </div>
        </div>
    @else
        {{-- Durum özeti --}}
        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="stat-card">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-ink-subtle">Durum</p>
                <p class="mt-2 text-2xl font-semibold {{ $hasDebt ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                    {{ $hasDebt ? 'Borçlu' : 'Güncel' }}
                </p>
                <p class="mt-2 text-xs text-ink-muted">
                    @if ($paidThrough)
                        {{ $paidThrough }} yılına kadar ödendi
                    @else
                        Henüz ödeme kaydı yok
                    @endif
                </p>
            </div>

            <div class="stat-card">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-ink-subtle">Toplam borç</p>
                <p class="mt-2 text-2xl font-semibold text-ink">₺{{ number_format($debtTotal, 0, ',', '.') }}</p>
                @if ($interestTotal > 0)
                    <p class="mt-1 text-xs" style="color: var(--status-warn)">
                        ₺{{ number_format($interestTotal, 2, ',', '.') }} gecikme faizi dahil
                    </p>
                @endif
                <p class="mt-2 text-xs text-ink-muted">
                    {{ $outstanding->count() > 0 ? $outstanding->count() . ' yıl ödenmedi' : 'Ödenmemiş yıl yok' }}
                </p>
            </div>

            <div class="stat-card">
                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-ink-subtle">Bugüne kadar ödenen</p>
                <p class="mt-2 text-2xl font-semibold text-ink">₺{{ number_format($paidTotal, 0, ',', '.') }}</p>
                <p class="mt-2 text-xs text-ink-muted">{{ $dues->where('status', 'paid')->count() }} yıl</p>
            </div>
        </div>

        {{-- Borç uyarısı --}}
        @if ($hasDebt)
            <div class="alert-soft mb-6 border-amber-200 bg-amber-50 text-amber-900 ring-amber-200 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-100 dark:ring-amber-800">
                <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                <div>
                    <p class="font-semibold">Borcunuz ödenene kadar tesis müracaatınız işleme alınamaz</p>
                    <p class="mt-1 text-sm">
                        İçinde bulunulan yıl dahil önceki yıllara ait aidat borcu bulunan üyelerin müracaat
                        formları, borç ödenmediği sürece değerlendirmeye alınmaz. Ödemenizi aşağıdaki hesaplardan
                        birine yaptıktan sonra Dernek ile iletişime geçin; kaydınız güncellendiğinde bu sayfada
                        görünecektir.
                    </p>
                </div>
            </div>
        @endif

        {{-- Aidat geçmişi --}}
        <div class="surface mb-6 overflow-hidden">
            <div class="border-b border-line px-5 py-4">
                <h2 class="text-base font-semibold text-ink">Aidat geçmişi</h2>
                <p class="text-xs text-ink-muted">Tahsilat kayıtları Dernek tarafından girilir.</p>
            </div>

            @if ($dues->isEmpty())
                <div class="empty-state !py-12">
                    <p class="text-sm text-ink-muted">Henüz aidat kaydınız bulunmuyor.</p>
                    <p class="text-xs text-ink-subtle">Tahakkuk açıldığında bu listede görünecektir.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Yıl</th>
                                <th>Tutar</th>
                                <th>Gecikme faizi</th>
                                <th>Toplam</th>
                                <th>Durum</th>
                                <th>Ödeme tarihi</th>
                                <th>Yöntem</th>
                                <th>Makbuz</th>
                                <th>Ödeme</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($dues as $due)
                                <tr>
                                    <td class="font-medium tabular-nums">{{ $due->year }}</td>
                                    <td class="tabular-nums"><x-money :value="$due->amount" /></td>
                                    <td class="tabular-nums">
                                        @if ($due->interestAmount() > 0)
                                            <span style="color: var(--status-warn)"><x-money :value="$due->interestAmount()" /></span>
                                            @unless ($due->isSettled())
                                                <span class="block text-[10px] text-ink-subtle">{{ $due->lateMonths() }} ay</span>
                                            @endunless
                                        @else
                                            <span class="text-ink-subtle">—</span>
                                        @endif
                                    </td>
                                    <td class="tabular-nums font-medium"><x-money :value="$due->totalDue()" /></td>
                                    <td><span class="badge-{{ $due->statusTone() }}">{{ $due->statusLabel() }}</span></td>
                                    <td class="text-xs text-ink-muted">{{ $due->paid_at?->translatedFormat('d F Y') ?? '—' }}</td>
                                    <td class="text-xs text-ink-muted">{{ $due->methodLabel() ?? '—' }}</td>
                                    <td class="text-xs text-ink-muted">{{ $due->receipt_no ?? '—' }}</td>
                                    <td>
                                        @if ($due->status === 'unpaid')
                                            <button type="button" @click="odenecek = {{ Illuminate\Support\Js::from([
                                                'id' => $due->id,
                                                'yil' => $due->year,
                                                'tutar' => number_format($due->totalDue(), 2, ',', '.'),
                                            ]) }}" class="btn-accent !px-3 !py-1.5 text-xs">Öde</button>
                                        @elseif ($due->status === 'review')
                                            <span class="text-[11px] text-ink-muted">Dekont incelemede</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Ödeme kanalları --}}
        @if ($hasDebt && ! empty($bankAccounts))
            <div class="surface overflow-hidden">
                <div class="border-b border-line px-5 py-4">
                    <h2 class="text-base font-semibold text-ink">Dernek banka hesapları</h2>
                    <p class="text-xs text-ink-muted">Açıklama kısmına ad soyad ve üyelik numaranızı yazın.</p>
                </div>
                <div class="divide-y divide-line">
                    @foreach ($bankAccounts as $account)
                        <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                            <div>
                                <p class="text-sm font-medium text-ink">{{ $account['bank'] }}</p>
                                <p class="text-[11px] text-ink-muted">{{ $account['branch'] ?? '' }}</p>
                            </div>
                            <x-iban :value="$account['iban']" />
                        </div>
                    @endforeach
                </div>
                @if ($member->membership_no)
                    <p class="border-t border-line bg-surface-alt px-5 py-3 text-xs text-ink-muted">
                        Üyelik numaranız: <span class="font-semibold text-ink">{{ $member->membership_no }}</span>
                    </p>
                @endif
            </div>
        @endif
    @endif
        {{-- Havale ile aidat ödeme --}}
        <template x-teleport="body">
            <div x-show="odenecek" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4"
                 @keydown.escape.window="odenecek = null">
                <div class="modal-scrim" @click="odenecek = null"></div>
                <div class="modal-panel" x-transition>
                    <h3 class="font-display text-lg font-semibold text-ink">
                        <span x-text="odenecek?.yil"></span> aidatını öde
                    </h3>
                    <p class="mt-1 text-sm text-ink-muted">
                        Ödenecek tutar: <strong class="text-ink">₺<span x-text="odenecek?.tutar"></span></strong>
                        <span class="block text-xs">Gecikme faizi varsa dahildir.</span>
                    </p>

                    @if (! empty($bankAccounts))
                        <div class="mt-4 overflow-hidden rounded-xl border border-line">
                            <p class="bg-surface-alt px-4 py-2 text-xs font-semibold uppercase tracking-wide text-ink-muted">Dernek hesapları</p>
                            <div class="divide-y divide-line">
                                @foreach ($bankAccounts as $account)
                                    <div class="px-4 py-2.5">
                                        <p class="text-sm font-medium text-ink">{{ $account['bank'] }}</p>
                                        <x-iban :value="$account['iban']" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <form method="POST" :action="'{{ url('panel/aidatlarim') }}/' + odenecek?.id + '/havale'"
                          enctype="multipart/form-data" class="mt-4 space-y-4">
                        @csrf
                        <div>
                            <label class="field-label">Banka dekontu</label>
                            <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" required
                                   class="field-input !py-2 file:mr-3 file:rounded-lg file:border-0 file:bg-accent-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                            <p class="field-hint">JPG, PNG veya PDF · en fazla 5 MB.</p>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" @click="odenecek = null" class="btn-secondary flex-1">Vazgeç</button>
                            <button type="submit" class="btn-accent flex-1">Dekontu Gönder</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>
</x-layouts.customer>
