<x-layouts.focus title="3D Secure Doğrulama">

    <div class="surface overflow-hidden">
        <div class="flex items-center gap-3 border-b border-line bg-chrome px-6 py-4 text-white">
            <svg class="h-5 w-5 text-accent-300" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
            <div>
                <p class="text-sm font-semibold">3D Secure Doğrulama</p>
                <p class="text-[11px] text-chrome-muted">Sanal POS benzetim ortamı</p>
            </div>
        </div>

        <div class="border-b border-amber-100 bg-amber-50 px-6 py-3">
            <p class="text-xs leading-relaxed text-amber-900">
                <span class="font-semibold">Test ortamı.</span> Banka sanal POS bilgileri henüz tanımlanmadığı için
                ödeme benzetim ile yürütülüyor. <code class="rounded bg-amber-100 px-1">.env</code> dosyasına banka
                bilgileri girilip <code class="rounded bg-amber-100 px-1">PAYMENT_DRIVER=nestpay</code> yapıldığında
                bu ekranın yerini bankanın gerçek 3D Secure sayfası alır.
            </p>
        </div>

        <div class="divide-y divide-line">
            <div class="flex justify-between px-6 py-3.5 text-sm">
                <span class="text-ink-muted">İşyeri</span>
                <span class="font-medium text-ink">SSK Sosyal Tesisleri</span>
            </div>
            <div class="flex justify-between px-6 py-3.5 text-sm">
                <span class="text-ink-muted">Başvuru</span>
                <span class="font-medium text-ink">{{ $payment->reservation->code }}</span>
            </div>
            <div class="flex justify-between px-6 py-3.5 text-sm">
                <span class="text-ink-muted">Ödeme türü</span>
                <span class="font-medium text-ink">
                    {{ $payment->kindLabel() }}@if ($payment->installment > 1) · {{ $payment->installment }} taksit @endif
                </span>
            </div>
            <div class="flex justify-between px-6 py-3.5 text-sm">
                <span class="text-ink-muted">Sipariş no</span>
                <span class="font-mono text-xs text-ink">{{ $payment->reference_no }}</span>
            </div>
            <div class="flex items-center justify-between bg-surface-alt px-6 py-4">
                <span class="font-semibold text-ink">Tutar</span>
                <x-money :value="$payment->amount" class="font-display text-xl font-semibold text-accent-700 dark:text-accent-300" />
            </div>
        </div>

        <div class="space-y-3 p-6">
            <form method="POST" action="{{ route('payment.callback', $payment) }}">
                @csrf
                <input type="hidden" name="decision" value="approve">
                <button type="submit" class="btn-accent w-full py-3">Ödemeyi Onayla</button>
            </form>

            <form method="POST" action="{{ route('payment.callback', $payment) }}">
                @csrf
                <input type="hidden" name="decision" value="decline">
                <button type="submit" class="btn-secondary w-full">İşlemi İptal Et</button>
            </form>
        </div>
    </div>
</x-layouts.focus>
