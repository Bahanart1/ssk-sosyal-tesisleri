<x-layouts.admin title="Üye Adına Rezervasyon">

    <div x-data="{
            facilities: @js($facilities->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'room_types' => $f->roomTypes->map(fn ($rt) => ['id' => $rt->id, 'name' => $rt->name, 'capacity' => $rt->capacity()])->values(),
                'periods' => $f->periods->map(fn ($p) => [
                    'id' => $p->id, 'label' => $p->label(), 'range' => $p->dateRange(),
                    'combines_with_id' => $p->combines_with_id, 'open' => (bool) $p->is_open,
                ])->values(),
            ])),
            facilityId: null,
            periodId: null,
            roomTypeId: null,
            guests: [{}],
            get facility() { return this.facilities.find(f => f.id == this.facilityId) ?? null },
            get periods() { return this.facility?.periods ?? [] },
            get roomTypes() { return this.facility?.room_types ?? [] },
            get selectedPeriod() { return this.periods.find(p => p.id == this.periodId) ?? null },
            get partner() { const c = this.selectedPeriod?.combines_with_id; return c ? this.periods.find(p => p.id == c) : null },
            get selectedRoomType() { return this.roomTypes.find(r => r.id == this.roomTypeId) ?? null },
            get capacity() { return this.selectedRoomType?.capacity ?? 0 },
            uyari: '',
            kisiEkle() {
                if (!this.selectedRoomType) {
                    this.uyari = 'Önce tesis ve oda tipi seçin; kişi sayısı oda kapasitesine göre sınırlanır.';
                    return;
                }
                if (this.guests.length >= this.capacity) {
                    this.uyari = this.selectedRoomType.name + ' en fazla ' + this.capacity +
                        ' kişiliktir. Daha kalabalık bir grup için daha büyük bir oda tipi seçin.';
                    return;
                }
                this.uyari = '';
                this.guests.push({});
            },
            kisiCikar(i) {
                if (this.guests.length <= 1) {
                    this.uyari = 'Rezervasyonda en az bir kişi bulunmalıdır.';
                    return;
                }
                this.uyari = '';
                this.guests.splice(i, 1);
            },
         }" class="mx-auto max-w-4xl">

        <a href="{{ $member ? route('admin.customers.show', $member) : route('admin.reservations.index') }}" class="back-link">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Geri
        </a>

        <div class="mt-4 mb-6">
            <p class="section-label">Yönetim</p>
            <h1 class="page-title mt-1">Üye adına rezervasyon</h1>
            <p class="page-subtitle">
                Telefonla gelen talepler için. Belge zorunluluğu uygulanmaz; kayıt inceleniyor
                durumunda açılır ve tutarı düzenleme ekranından gözden geçirirsiniz.
            </p>
        </div>

        @if ($errors->any())
            <div class="alert-soft mb-6 border-red-200 bg-red-50 text-red-700 ring-red-200">
                <div>
                    <p class="font-semibold">Oluşturulamadı</p>
                    <ul class="mt-1 list-disc space-y-0.5 pl-4">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if (! $member)
            <div class="surface p-10 text-center">
                <p class="text-sm text-ink-muted">Rezervasyon oluşturmak için önce üyeyi seçin.</p>
                <a href="{{ route('admin.customers.index') }}" class="btn-primary mt-4">Üyeler listesine git</a>
            </div>
        @else
            <form method="POST" action="{{ route('admin.reservations.store') }}" class="space-y-6">
                @csrf
                <input type="hidden" name="user_id" value="{{ $member->id }}">

                <div class="surface overflow-hidden">
                    <div class="border-b border-line px-6 py-4">
                        <h2 class="font-display text-lg font-semibold text-ink">{{ $member->name }}</h2>
                        <p class="text-xs text-ink-muted">
                            {{ $member->membership_no ?? $member->maskedTcNo() }} ·
                            {{ $member->customerGroup?->name ?? 'Grup atanmadı' }}
                            @if ($member->hasDuesDebt())
                                · <span class="font-semibold" style="color: var(--status-warn)">Aidat borcu var</span>
                            @endif
                        </p>
                    </div>

                    <div class="grid gap-5 p-6 sm:grid-cols-2">
                        <div>
                            <label class="field-label">Tesis</label>
                            <select x-model="facilityId" @change="periodId = null; roomTypeId = null" class="field-input">
                                <option value="">Seçin</option>
                                <template x-for="f in facilities" :key="f.id">
                                    <option :value="f.id" x-text="f.name"></option>
                                </template>
                            </select>
                        </div>

                        <div>
                            <label class="field-label">Oda tipi</label>
                            <select name="room_type_id" x-model="roomTypeId" class="field-input">
                                <option value="">Seçin</option>
                                <template x-for="rt in roomTypes" :key="rt.id">
                                    <option :value="rt.id" x-text="rt.name + ' (' + rt.capacity + ' kişi)'"></option>
                                </template>
                            </select>
                        </div>

                        <div>
                            <label class="field-label">Devre</label>
                            <select name="period_id" x-model="periodId" class="field-input">
                                <option value="">Seçin</option>
                                <template x-for="p in periods" :key="p.id">
                                    <option :value="p.id" x-text="p.label + ' · ' + p.range + (p.open ? '' : ' (kapalı)')"></option>
                                </template>
                            </select>
                            <p class="field-hint">Yönetici kapalı devreye de kayıt açabilir.</p>
                        </div>

                        <div>
                            <label class="field-label">İkinci devre <span class="font-normal text-ink-subtle">(birleşik)</span></label>
                            <select name="second_period_id" class="field-input" :disabled="!partner">
                                <option value="">Yok</option>
                                <template x-if="partner">
                                    <option :value="partner.id" x-text="partner.label + ' · ' + partner.range"></option>
                                </template>
                            </select>
                            <p class="field-hint" x-text="partner ? 'Bu devre ' + partner.label + ' ile birleştirilebilir.' : 'Seçilen devre birleştirilemiyor.'"></p>
                        </div>
                    </div>
                </div>

                <div class="surface overflow-hidden">
                    <div class="flex items-center justify-between border-b border-line px-6 py-4">
                        <h2 class="font-display text-lg font-semibold text-ink">Konaklayacak kişiler</h2>
                        <span class="text-xs text-ink-muted"
                              x-text="capacity ? guests.length + ' / ' + capacity + ' kişi' : guests.length + ' kişi'"></span>
                    </div>

                    <div class="divide-y divide-line">
                        <template x-for="(g, i) in guests" :key="i">
                            <div class="p-5">
                                <div class="mb-3 flex items-center justify-between">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted" x-text="(i + 1) + '. kişi'"></p>
                                    <button type="button" @click="kisiCikar(i)"
                                            class="text-xs font-medium text-red-600"
                                            :class="guests.length <= 1 ? 'opacity-40' : ''">Çıkar</button>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="field-label">Ad soyad</label>
                                        <input type="text" :name="'guests[' + i + '][full_name]'" class="field-input">
                                    </div>
                                    <div>
                                        <label class="field-label">TC kimlik no</label>
                                        <input type="text" maxlength="11" inputmode="numeric" :name="'guests[' + i + '][tc_no]'" class="field-input">
                                    </div>
                                    <div>
                                        <label class="field-label">Doğum tarihi</label>
                                        <input type="date" :name="'guests[' + i + '][birth_date]'" class="field-input">
                                    </div>
                                    <div>
                                        <label class="field-label">Yakınlık</label>
                                        <select :name="'guests[' + i + '][relation]'" class="field-input">
                                            @foreach ($relations as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="field-label">Müşteri grubu</label>
                                        <select :name="'guests[' + i + '][customer_group_id]'" class="field-input">
                                            @foreach ($groups as $group)
                                                <option value="{{ $group->id }}" @selected($group->id === $member->customer_group_id)>{{ $group->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="border-t border-line p-5">
                        <button type="button" @click="kisiEkle()" class="btn-secondary w-full border-dashed">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            Kişi ekle
                        </button>

                        <p x-show="uyari" x-cloak x-transition
                           class="mt-3 rounded-lg px-3 py-2 text-xs font-medium"
                           style="background: var(--c-surface-sunken); color: var(--status-warn)"
                           x-text="uyari"></p>

                        <p x-show="!uyari && capacity" x-cloak class="mt-2 text-[11px] text-ink-subtle"
                           x-text="'Seçilen oda ' + capacity + ' kişiliktir.'"></p>
                    </div>
                </div>

                <div class="surface p-6">
                    <label class="field-label">Not</label>
                    <textarea name="note" rows="2" class="field-input" placeholder="Örn. telefonla alındı, kimlik belgeleri elden teslim edilecek">{{ old('note') }}</textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.customers.show', $member) }}" class="btn-secondary">Vazgeç</a>
                    <button type="submit" class="btn-primary">Rezervasyonu oluştur</button>
                </div>
            </form>
        @endif
    </div>
</x-layouts.admin>
