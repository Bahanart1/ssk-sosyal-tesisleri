<x-layouts.guest title="Admin Girişi">
    <div class="mb-8">
        <span class="badge-teal mb-3">Yönetim paneli</span>
        <h1 class="mt-2 font-display text-3xl font-semibold tracking-tight text-navy-900">Yönetici girişi</h1>
        <p class="page-subtitle">Rezervasyonları, müşterileri ve tesisleri yönetmek için oturum açın.</p>
    </div>

    @if ($errors->any())
        <div class="alert-soft mb-5 border-red-200 bg-red-50 text-red-700 ring-red-200">
            <svg class="mt-0.5 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            <p class="font-medium">{{ $errors->first() }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.login') }}" class="space-y-5" x-data="{ showPass: false }">
        @csrf
        <div>
            <label class="field-label" for="email">E-posta</label>
            <input id="email" name="email" type="email" required autofocus value="{{ old('email') }}"
                   autocomplete="username"
                   class="field-input" placeholder="admin@ssk-tesisleri.gov.tr">
        </div>
        <div>
            <label class="field-label" for="password">Şifre</label>
            <div class="relative">
                <input id="password" name="password" :type="showPass ? 'text' : 'password'" required
                       autocomplete="current-password"
                       class="field-input pr-11" placeholder="Şifrenizi girin">
                <button type="button" @click="showPass = !showPass"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-stone-400 hover:text-navy-700"
                        :aria-label="showPass ? 'Şifreyi gizle' : 'Şifreyi göster'">
                    <svg x-show="!showPass" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                    <svg x-show="showPass" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                </button>
            </div>
        </div>
        <button type="submit" class="btn-primary w-full py-3 text-[15px]">Giriş Yap</button>
    </form>

    <p class="mt-8 text-center text-sm text-stone-500">
        Müşteri misiniz?
        <a href="{{ route('login') }}" class="font-semibold text-navy-800 underline decoration-teal-400/60 underline-offset-4 hover:text-teal-700">Müşteri girişi</a>
    </p>
</x-layouts.guest>
