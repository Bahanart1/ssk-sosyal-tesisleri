<x-layouts.focus title="Şifrenizi Belirleyin">

    <div class="surface overflow-hidden">
        <div class="border-b border-line px-6 py-5">
            <h1 class="font-display text-xl font-semibold text-ink">Yeni şifrenizi belirleyin</h1>
            <p class="mt-1.5 text-sm leading-relaxed text-ink-muted">
                Güvenliğiniz için şifrenizin TC kimlik numaranızdan farklı olması gerekiyor.
                Bu adımı tamamlamadan panele geçemezsiniz.
            </p>
        </div>

        @if ($errors->any())
            <div class="alert-soft mx-6 mt-5 border-red-200 bg-red-50 text-red-700 ring-red-200 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-800">
                <p class="font-medium">{{ $errors->first() }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('customer.password.force.update') }}" class="space-y-4 p-6">
            @csrf

            <div>
                <label for="password" class="field-label">Yeni şifre</label>
                <input id="password" type="password" name="password" required minlength="8"
                       autocomplete="new-password" autofocus class="field-input">
                <p class="field-hint">En az 8 karakter. Tahmin edilmesi zor bir şifre seçin.</p>
            </div>

            <div>
                <label for="password_confirmation" class="field-label">Yeni şifre (tekrar)</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required
                       autocomplete="new-password" class="field-input">
            </div>

            <button type="submit" class="btn-accent w-full">Şifreyi Kaydet ve Devam Et</button>
        </form>

        <div class="border-t border-line px-6 py-3">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-ghost w-full text-xs">Çıkış yap</button>
            </form>
        </div>
    </div>

</x-layouts.focus>
