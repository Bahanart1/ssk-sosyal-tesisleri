<x-layouts.admin title="Ödemeler">

    <div class="mb-6">
        <p class="section-label">Yönetim</p>
        <h1 class="page-title mt-1">Ödemeler</h1>
        <p class="page-subtitle">
            Havale dekontlarını doğrulayın, sanal POS işlemlerini izleyin.
            @if ($pendingCount > 0)
                <span class="font-semibold text-amber-700">{{ $pendingCount }} dekont doğrulama bekliyor.</span>
            @endif
        </p>
    </div>

    <form method="GET" class="surface mb-6 flex flex-wrap items-end gap-3 p-4">
        <div>
            <label class="field-label">Durum</label>
            <select name="status" class="field-input">
                <option value="">Tümü</option>
                <option value="pending" @selected(request('status') === 'pending')>Bekliyor</option>
                <option value="success" @selected(request('status') === 'success')>Onaylandı</option>
                <option value="failed" @selected(request('status') === 'failed')>Başarısız</option>
            </select>
        </div>
        <div>
            <label class="field-label">Tür</label>
            <select name="kind" class="field-input">
                <option value="">Tümü</option>
                <option value="deposit" @selected(request('kind') === 'deposit')>Peşinat</option>
                <option value="balance" @selected(request('kind') === 'balance')>Bakiye</option>
            </select>
        </div>
        <div>
            <label class="field-label">Yöntem</label>
            <select name="method" class="field-input">
                <option value="">Tümü</option>
                <option value="card" @selected(request('method') === 'card')>Kart</option>
                <option value="bank_transfer" @selected(request('method') === 'bank_transfer')>Havale</option>
            </select>
        </div>
        <button type="submit" class="btn-primary">Filtrele</button>
        @if (request()->hasAny(['status', 'kind', 'method']))
            <a href="{{ route('admin.payments.index') }}" class="btn-ghost">Temizle</a>
        @endif
    </form>

    <div class="surface overflow-hidden">
        @if ($payments->isEmpty())
            <p class="px-6 py-16 text-center text-sm text-ink-subtle">Ödeme kaydı bulunamadı.</p>
        @else
            <ul class="divide-y divide-line">
                @foreach ($payments as $payment)
                    <li x-data="{ rejectOpen: false }" class="p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('admin.reservations.show', $payment->reservation) }}"
                                       class="font-semibold text-ink hover:text-accent-700 dark:hover:text-accent-300">
                                        {{ $payment->reservation->user->name }}
                                    </a>
                                    <span class="badge-gray !py-0.5 !text-[10px]">{{ $payment->kindLabel() }}</span>
                                    <x-status-badge :status="$payment->status" :label="$payment->statusLabel()" />
                                </div>
                                <p class="mt-1 text-xs text-ink-muted">
                                    {{ $payment->reservation->code }} · {{ $payment->methodLabel() }}
                                    @if ($payment->installment > 1) · {{ $payment->installment }} taksit @endif
                                    · {{ $payment->reference_no }}
                                    · {{ ($payment->paid_at ?? $payment->created_at)->translatedFormat('d F Y H:i') }}
                                </p>
                                @if ($payment->failure_reason)
                                    <p class="mt-1 text-xs text-red-600">{{ $payment->failure_reason }}</p>
                                @endif
                                @if ($payment->verifier)
                                    <p class="mt-1 text-xs text-ink-subtle">{{ $payment->verifier->name }} · {{ $payment->verified_at?->translatedFormat('d F Y H:i') }}</p>
                                @endif
                            </div>
                            <x-money :value="$payment->amount" class="shrink-0 font-display text-lg font-semibold text-ink" />
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            @if ($payment->receipt_path)
                                <a href="{{ route('documents.receipt', $payment) }}" target="_blank" rel="noopener"
                                   class="btn-secondary !px-3 !py-1.5 text-xs">Dekontu Görüntüle</a>
                            @endif

                            @if ($payment->method === 'bank_transfer' && $payment->status === 'pending')
                                <form method="POST" action="{{ route('admin.payments.verify', $payment) }}">
                                    @csrf
                                    <button class="btn-accent !px-3 !py-1.5 text-xs">Doğrula</button>
                                </form>
                                <button type="button" @click="rejectOpen = !rejectOpen" class="btn-ghost !px-3 !py-1.5 text-xs !text-red-600">Reddet</button>
                            @endif
                        </div>

                        <form x-show="rejectOpen" x-cloak method="POST" action="{{ route('admin.payments.reject', $payment) }}"
                              class="mt-3 flex gap-2">
                            @csrf
                            <input type="text" name="reason" required placeholder="Red gerekçesi" class="field-input !py-1.5 text-xs">
                            <button class="btn-danger !px-3 !py-1.5 text-xs shrink-0">Reddet</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="mt-6">{{ $payments->links() }}</div>
</x-layouts.admin>
