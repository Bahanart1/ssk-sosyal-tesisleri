<x-layouts.focus title="Bankaya Yönlendiriliyorsunuz">

    <div class="surface p-8 text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-teal-50 text-teal-700 ring-1 ring-teal-200">
            <svg class="h-6 w-6 animate-pulse" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
        </div>

        <h1 class="mt-4 font-display text-xl font-semibold text-navy-900">Güvenli ödeme sayfasına yönlendiriliyorsunuz</h1>
        <p class="mt-2 text-sm text-stone-500">
            {{ $payment->kindLabel() }} tutarı <x-money :value="$payment->amount" class="font-semibold text-navy-800" />.
            Lütfen bu sayfayı kapatmayın.
        </p>

        <form id="gateway-form" method="{{ $redirect->method }}" action="{{ $redirect->url }}">
            @foreach ($redirect->fields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <noscript>
                <button type="submit" class="btn-accent mt-6 w-full py-3">Ödeme sayfasına git</button>
            </noscript>
        </form>

        <p class="mt-6 text-xs text-stone-400">
            Yönlendirilmezseniz
            <button type="button" onclick="document.getElementById('gateway-form').submit()" class="font-semibold text-teal-700 underline">buraya tıklayın</button>.
        </p>
    </div>

    <script>
        document.getElementById('gateway-form').submit();
    </script>
</x-layouts.focus>
