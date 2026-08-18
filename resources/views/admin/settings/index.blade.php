<x-layouts.admin title="Parametreler">

    <div class="mb-6">
        <p class="section-label">Yönetim</p>
        <h1 class="page-title mt-1">Parametreler</h1>
        <p class="page-subtitle">
            Yönetim Kurulunca her yıl belirlenen peşinat tutarları, ücretlendirme oranları ve ödeme koşulları.
            Değişiklikler yeni hesaplamalarda geçerli olur; onaylanmış başvuruların tutarı değişmez.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" x-data="{ tiers: {{ Illuminate\Support\Js::from(array_values($tiers)) }}, accounts: {{ Illuminate\Support\Js::from(array_values($bankAccounts)) }} }">
        @csrf
        @method('PUT')

        <div class="space-y-6">

            {{-- Peşinat --}}
            <div class="surface overflow-hidden">
                <div class="border-b border-line px-6 py-4">
                    <h2 class="font-display text-lg font-semibold text-ink">Peşinat tutarları</h2>
                    <p class="text-xs text-ink-muted">Oda veya villa başına, müracaat sırasında peşin olarak ödenir.</p>
                </div>
                <div class="grid gap-5 p-6 sm:grid-cols-2">
                    <div>
                        <label class="field-label">Bir devre (₺)</label>
                        <input type="number" step="0.01" min="0" name="deposit_one_period" value="{{ $deposits['one_period'] }}" required class="field-input">
                    </div>
                    <div>
                        <label class="field-label">İki devre (₺)</label>
                        <input type="number" step="0.01" min="0" name="deposit_two_periods" value="{{ $deposits['two_periods'] }}" required class="field-input">
                    </div>
                    <div>
                        <label class="field-label">Tek kişi · bir devre (₺)</label>
                        <input type="number" step="0.01" min="0" name="deposit_one_period_single" value="{{ $deposits['one_period_single'] }}" required class="field-input">
                    </div>
                    <div>
                        <label class="field-label">Tek kişi · iki devre (₺)</label>
                        <input type="number" step="0.01" min="0" name="deposit_two_periods_single" value="{{ $deposits['two_periods_single'] }}" required class="field-input">
                    </div>
                </div>
            </div>

            {{-- Müracaat tarihine göre ilave ücret --}}
            <div class="surface overflow-hidden">
                <div class="border-b border-line px-6 py-4">
                    <h2 class="font-display text-lg font-semibold text-ink">Müracaat tarihine göre ilave ücret</h2>
                    <p class="text-xs text-ink-muted">Kişi başı günlük olarak tablo ücretlerine eklenir. Bitiş tarihi boş bırakılırsa süresizdir.</p>
                </div>
                <div class="space-y-3 p-6">
                    <template x-for="(tier, index) in tiers" :key="index">
                        <div class="grid items-end gap-3 rounded-xl border border-line p-4 sm:grid-cols-4">
                            <div>
                                <label class="field-label">Başlangıç</label>
                                <input type="date" :name="'tiers['+index+'][from]'" x-model="tier.from" class="field-input">
                            </div>
                            <div>
                                <label class="field-label">Bitiş</label>
                                <input type="date" :name="'tiers['+index+'][to]'" x-model="tier.to" class="field-input">
                            </div>
                            <div>
                                <label class="field-label">İlave ücret (₺)</label>
                                <input type="number" step="0.01" min="0" :name="'tiers['+index+'][amount]'" x-model.number="tier.amount" required class="field-input">
                            </div>
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <label class="field-label">Açıklama</label>
                                    <input type="text" :name="'tiers['+index+'][label]'" x-model="tier.label" class="field-input">
                                </div>
                                <button type="button" @click="tiers.splice(index, 1)" x-show="tiers.length > 1"
                                        class="mb-1 rounded-lg p-2 text-red-600 hover:bg-red-50" title="Kademeyi sil">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>
                    </template>
                    <button type="button" @click="tiers.push({from: '', to: '', amount: 0, label: ''})" class="btn-secondary border-dashed">
                        Kademe ekle
                    </button>
                </div>
            </div>

            {{-- Oranlar --}}
            <div class="surface overflow-hidden">
                <div class="border-b border-line px-6 py-4">
                    <h2 class="font-display text-lg font-semibold text-ink">Ücretlendirme oranları</h2>
                    <p class="text-xs text-ink-muted">Ondalık olarak girin (örn. 0,60 → %60).</p>
                </div>
                <div class="grid gap-5 p-6 sm:grid-cols-3">
                    <div>
                        <label class="field-label">0-5 yaş yemek oranı</label>
                        <input type="number" step="0.01" min="0" max="1" name="child_meal_rate" value="{{ $rates['child_meal'] }}" required class="field-input">
                        <p class="field-hint">Yemek talep edilirse uygulanır; yatak ücreti alınmaz.</p>
                    </div>
                    <div>
                        <label class="field-label">6-11 yaş oranı</label>
                        <input type="number" step="0.01" min="0" max="1" name="child_discount_rate" value="{{ $rates['child_discount'] }}" required class="field-input">
                    </div>
                    <div>
                        <label class="field-label">Zemin kat indirimi</label>
                        <input type="number" step="0.01" min="0" max="1" name="ground_floor_rate" value="{{ $rates['ground_floor'] }}" required class="field-input">
                    </div>
                </div>
            </div>

            {{-- Ödeme koşulları --}}
            <div class="surface overflow-hidden">
                <div class="border-b border-line px-6 py-4">
                    <h2 class="font-display text-lg font-semibold text-ink">Ödeme ve iptal koşulları</h2>
                </div>
                <div class="grid gap-5 p-6 sm:grid-cols-2">
                    <div>
                        <label class="field-label">Bakiye ödeme süresi (gün)</label>
                        <input type="number" min="1" max="120" name="balance_due_days" value="{{ $terms['balance_due_days'] }}" required class="field-input">
                        <p class="field-hint">Yer tahsisi bildiriminden itibaren.</p>
                    </div>
                    <div>
                        <label class="field-label">İptal için asgari gün</label>
                        <input type="number" min="0" max="120" name="cancellation_min_days" value="{{ $terms['cancellation_min_days'] }}" required class="field-input">
                        <p class="field-hint">Devre başlangıcına kalması gereken süre.</p>
                    </div>
                    <div>
                        <label class="field-label">İptal kesintisi (₺)</label>
                        <input type="number" min="0" step="0.01" name="refund_cancellation_fee" value="{{ $terms['refund_cancellation_fee'] }}" required class="field-input">
                        <p class="field-hint">Üye iptalinde iadeden düşülen kırtasiye ve hizmet bedeli. Yer tahsis edilemeyen başvurularda kesinti yapılmaz.</p>
                    </div>
                </div>
            </div>

            {{-- Üyelik aidatı --}}
            <div class="surface overflow-hidden">
                <div class="border-b border-line px-6 py-4">
                    <h2 class="font-display text-lg font-semibold text-ink">Üyelik aidatı</h2>
                    <p class="text-xs text-ink-muted">Yıllık tahakkuk oluştururken varsayılan olarak kullanılır.</p>
                </div>
                <div class="grid gap-5 p-6 sm:grid-cols-2">
                    <div>
                        <label class="field-label">Yıllık aidat tutarı (₺)</label>
                        <input type="number" step="0.01" min="0" name="dues_annual_amount" value="{{ $duesAmount }}" required class="field-input">
                        <p class="field-hint">Aidatlar ekranından yıl bazında farklı bir tutarla da tahakkuk açabilirsiniz.</p>
                    </div>
                </div>
            </div>

            {{-- Banka hesapları --}}
            <div class="surface overflow-hidden">
                <div class="border-b border-line px-6 py-4">
                    <h2 class="font-display text-lg font-semibold text-ink">Banka hesapları</h2>
                    <p class="text-xs text-ink-muted">Peşinat ve bakiye havalesi için üyelere gösterilir.</p>
                </div>
                <div class="space-y-3 p-6">
                    <template x-for="(account, index) in accounts" :key="index">
                        <div class="grid items-end gap-3 rounded-xl border border-line p-4 sm:grid-cols-[1fr_1fr_1.4fr_auto]">
                            <div>
                                <label class="field-label">Banka</label>
                                <input type="text" :name="'bank_accounts['+index+'][bank]'" x-model="account.bank" class="field-input">
                            </div>
                            <div>
                                <label class="field-label">Şube</label>
                                <input type="text" :name="'bank_accounts['+index+'][branch]'" x-model="account.branch" class="field-input">
                            </div>
                            <div>
                                <label class="field-label">IBAN</label>
                                <input type="text" :name="'bank_accounts['+index+'][iban]'" x-model="account.iban" class="field-input font-mono text-xs">
                            </div>
                            <button type="button" @click="accounts.splice(index, 1)"
                                    class="mb-1 rounded-lg p-2 text-red-600 hover:bg-red-50" title="Hesabı sil">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </template>
                    <button type="button" @click="accounts.push({bank: '', branch: '', iban: ''})" class="btn-secondary border-dashed">
                        Hesap ekle
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <button type="submit" class="btn-primary px-8 py-3">Parametreleri Kaydet</button>
        </div>
    </form>
</x-layouts.admin>
