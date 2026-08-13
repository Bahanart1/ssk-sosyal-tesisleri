<x-layouts.customer title="Aidatlarım">

    <div class="mb-6">
        <p class="section-label">Üyelik</p>
        <h1 class="page-title mt-1">Aidatlarım</h1>
        <p class="page-subtitle">Yıl bazında aidat tahakkuk ve ödeme geçmişiniz.</p>
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
                                <th>Durum</th>
                                <th>Ödeme tarihi</th>
                                <th>Yöntem</th>
                                <th>Makbuz</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($dues as $due)
                                <tr>
                                    <td class="font-medium tabular-nums">{{ $due->year }}</td>
                                    <td class="tabular-nums"><x-money :value="$due->amount" /></td>
                                    <td><span class="badge-{{ $due->statusTone() }}">{{ $due->statusLabel() }}</span></td>
                                    <td class="text-xs text-ink-muted">{{ $due->paid_at?->translatedFormat('d F Y') ?? '—' }}</td>
                                    <td class="text-xs text-ink-muted">{{ $due->methodLabel() ?? '—' }}</td>
                                    <td class="text-xs text-ink-muted">{{ $due->receipt_no ?? '—' }}</td>
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
                            <p class="font-mono text-xs text-ink">{{ $account['iban'] }}</p>
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
</x-layouts.customer>
