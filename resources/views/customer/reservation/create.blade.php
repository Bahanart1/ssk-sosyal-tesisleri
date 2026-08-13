<x-layouts.customer title="Yeni Başvuru">

    <div x-data="applicationWizard()" class="mx-auto max-w-3xl">

        <div class="mb-8">
            <a href="{{ route('customer.dashboard') }}" class="back-link">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                Panelime dön
            </a>
            <p class="section-label mt-4">Tesis müracaatı</p>
            <h1 class="page-title mt-1">Yeni başvuru</h1>
            <p class="page-subtitle">Devreler pazar günü girişle başlar, takip eden cumartesi sona erer. Bir devre veya ardışık en fazla iki devre için başvurabilirsiniz.</p>
        </div>

        {{-- Adım göstergesi: numaralı noktalar, altında etkin adımın adı --}}
        <div class="mb-8">
            <ol class="flex items-center gap-1.5">
                <template x-for="(label, idx) in stepLabels" :key="idx">
                    <li class="flex flex-1 items-center gap-1.5">
                        <div class="stepper-dot"
                             :class="step > idx + 1 ? 'bg-teal-500 text-white' : (step === idx + 1 ? 'bg-navy-900 text-white shadow-soft scale-105' : 'bg-white text-stone-400 ring-1 ring-stone-200')"
                             :title="label" :aria-current="step === idx + 1 ? 'step' : null">
                            <template x-if="step > idx + 1">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                            </template>
                            <span x-show="step <= idx + 1" x-text="idx + 1"></span>
                        </div>
                        <div class="h-px flex-1 rounded bg-stone-200" :class="step > idx + 1 ? '!bg-teal-400' : ''" x-show="idx < stepLabels.length - 1"></div>
                    </li>
                </template>
            </ol>
            <p class="mt-3.5 text-sm font-semibold text-teal-700">
                <span x-text="'Adım ' + step + ' / ' + stepLabels.length"></span>
                <span class="text-navy-900" x-text="' · ' + stepLabels[step - 1]"></span>
            </p>
        </div>

        @if ($errors->any())
            <div class="alert-soft mb-6 border-red-200 bg-red-50 text-red-700 ring-red-200">
                <svg class="mt-0.5 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                <div>
                    <p class="font-semibold">Başvurunuz gönderilemedi</p>
                    <ul class="mt-1 list-disc space-y-0.5 pl-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-xs text-red-600/80">Güvenlik nedeniyle yüklediğiniz belgeler saklanmaz; lütfen kimlik belgelerini yeniden ekleyin.</p>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('customer.reservations.store') }}" enctype="multipart/form-data" @submit="submitting = true">
            @csrf
            <input type="hidden" name="room_type_id" :value="roomTypeId">
            <input type="hidden" name="period_id" :value="periodId">
            <input type="hidden" name="second_period_id" :value="secondPeriodId ?? ''">
            <input type="hidden" name="ground_floor_request" :value="groundFloorRequest ? 1 : 0">
            <input type="hidden" name="note" :value="note">
            <input type="hidden" name="deposit_method" :value="depositMethod">

            <div class="surface p-6 sm:p-8">

                {{-- ---------------------------------------------------------- --}}
                {{-- Adım 1 — Tesis --}}
                {{-- ---------------------------------------------------------- --}}
                <div x-show="step === 1">
                    <h2 class="font-display text-xl font-semibold text-navy-900">Tesis seçimi</h2>
                    <p class="mt-1 text-sm text-stone-500">Konaklamak istediğiniz tesisi seçin.</p>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <template x-for="f in facilities" :key="f.id">
                            <button type="button" @click="selectFacility(f)" class="choice-tile"
                                    :class="facilityId === f.id ? 'choice-tile-active' : 'choice-tile-idle'">
                                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-navy-900/5 text-teal-700">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21" /></svg>
                                </span>
                                <span class="mt-2 font-semibold text-navy-900" x-text="f.name"></span>
                                <span class="text-xs font-medium text-teal-700" x-text="f.location"></span>
                                <span class="mt-1 text-xs leading-relaxed text-stone-500" x-text="f.description"></span>
                                <span class="mt-2 text-[11px] text-stone-400" x-text="f.periods.length + ' devre başvuruya açık'"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- ---------------------------------------------------------- --}}
                {{-- Adım 2 — Devre --}}
                {{-- ---------------------------------------------------------- --}}
                <div x-show="step === 2" x-cloak>
                    <h2 class="font-display text-xl font-semibold text-navy-900">Devre seçimi</h2>
                    <p class="mt-1 text-sm text-stone-500">Bir devre seçin. Ardışık iki devre birleştirilebiliyorsa seçimden sonra ekleyebilirsiniz.</p>

                    <template x-if="periods.length === 0">
                        <div class="empty-state !py-12">
                            <p class="font-medium text-stone-500">Bu tesis için başvuruya açık devre bulunmuyor.</p>
                        </div>
                    </template>

                    <div class="mt-5 grid max-h-[26rem] gap-2 overflow-y-auto pr-1">
                        <template x-for="p in periods" :key="p.id">
                            <button type="button" @click="selectPeriod(p)"
                                    class="flex w-full items-center justify-between gap-3 rounded-xl2 border px-4 py-3.5 text-left transition-all"
                                    :class="periodId === p.id ? 'border-teal-500 bg-teal-50/60 shadow-glow ring-2 ring-teal-500/20' : 'border-stone-200 bg-white hover:border-navy-300'">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-navy-900" x-text="p.label"></p>
                                        <span x-show="p.is_discounted" class="badge-teal !py-0.5 !text-[10px]">İndirimli</span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-stone-500" x-text="p.date_range + ' · ' + p.nights + ' gün'"></p>
                                    <p x-show="p.note" x-cloak class="mt-1 text-[11px] text-amber-700" x-text="p.note"></p>
                                </div>
                                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full"
                                     :class="periodId === p.id ? 'bg-navy-900 text-white' : 'bg-sand-100 text-navy-500'">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                                </div>
                            </button>
                        </template>
                    </div>

                    {{-- Birleşen devre --}}
                    <template x-if="selectedPeriod && selectedPeriod.combinable_with">
                        <label class="mt-5 flex cursor-pointer items-start gap-3 rounded-xl border border-teal-100 bg-teal-50/50 px-4 py-3.5">
                            <input type="checkbox" :checked="secondPeriodId !== null" @change="toggleSecondPeriod()"
                                   class="mt-0.5 rounded border-stone-300 text-teal-600 focus:ring-teal-500">
                            <span class="text-sm">
                                <span class="font-semibold text-teal-900">İkinci devreyi de ekle</span>
                                <span class="block text-xs text-teal-800/80" x-text="selectedPeriod.combinable_label"></span>
                                <span class="mt-1 block text-[11px] text-teal-700/70">Ardışık iki devre birlikte 13 gün konaklama sağlar.</span>
                            </span>
                        </label>
                    </template>
                </div>

                {{-- ---------------------------------------------------------- --}}
                {{-- Adım 3 — Oda tipi --}}
                {{-- ---------------------------------------------------------- --}}
                <div x-show="step === 3" x-cloak>
                    <h2 class="font-display text-xl font-semibold text-navy-900">Oda tipi</h2>
                    <p class="mt-1 text-sm text-stone-500">Tesisin sunduğu konaklama seçeneklerinden birini belirleyin.</p>

                    <div class="mt-6 grid gap-3">
                        <template x-for="rt in roomTypes" :key="rt.id">
                            <button type="button" @click="selectRoomType(rt)" class="choice-tile"
                                    :class="roomTypeId === rt.id ? 'choice-tile-active' : 'choice-tile-idle'">
                                <div class="flex w-full items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-semibold text-navy-900" x-text="rt.name"></span>
                                            <span x-show="rt.kind === 'villa'" class="badge-amber !py-0.5 !text-[10px]">Villa</span>
                                            <span x-show="rt.is_ground_floor" class="badge-teal !py-0.5 !text-[10px]">%10 indirimli</span>
                                        </div>
                                        <span class="mt-1 block text-xs leading-relaxed text-stone-500" x-text="rt.description"></span>
                                    </div>
                                    <span class="shrink-0 rounded-lg bg-sand-100 px-2.5 py-1 text-xs font-semibold text-navy-700"
                                          x-text="rt.bed_count + ' yatak'"></span>
                                </div>
                            </button>
                        </template>
                    </div>

                    {{-- Mazeret nedeniyle alt kat talebi (Madde 5/6) --}}
                    <div class="mt-6 rounded-xl border border-stone-200/80 p-4">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" x-model="groundFloorRequest" class="mt-0.5 rounded border-stone-300 text-teal-600 focus:ring-teal-500">
                            <span class="text-sm">
                                <span class="font-semibold text-navy-900">Mazeretim nedeniyle alt kat veya zemin kat tahsis edilmesini istiyorum</span>
                                <span class="block text-xs text-stone-500">Ortopedik engel, yaşlılık veya sağlık gibi durumlar için. Varsa sağlık raporunuzu ekleyin.</span>
                            </span>
                        </label>

                        <div x-show="groundFloorRequest" x-cloak class="mt-4 space-y-4">
                            <div>
                                <label class="field-label">Mazeret açıklaması</label>
                                <textarea name="ground_floor_note" x-model="groundFloorNote" rows="2" class="field-input" placeholder="Durumunuzu kısaca açıklayın"></textarea>
                            </div>
                            <div>
                                <label class="field-label">Sağlık raporu <span class="font-normal text-stone-400">(opsiyonel)</span></label>
                                <input type="file" name="health_report" accept=".jpg,.jpeg,.png,.pdf" class="field-input !py-2 file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ---------------------------------------------------------- --}}
                {{-- Adım 4 — Kişiler --}}
                {{-- ---------------------------------------------------------- --}}
                <div x-show="step === 4" x-cloak>
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 class="font-display text-xl font-semibold text-navy-900">Konaklayacak kişiler</h2>
                            <p class="mt-1 text-sm text-stone-500">Her kişi için geçerli bir kimlik belgesi eklenmesi zorunludur.</p>
                        </div>
                        <span class="rounded-lg bg-sand-100 px-2.5 py-1 text-xs font-semibold text-navy-700"
                              x-text="guests.length + ' / ' + capacity + ' kişi'"></span>
                    </div>

                    <div class="mt-3 alert-soft border-teal-100 bg-teal-50/60 text-teal-900 ring-teal-100">
                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                        <p class="text-xs leading-relaxed">
                            Yaş hesabında devre başlangıç tarihi esas alınır. <strong>0-5 yaş</strong> için yatak ücreti alınmaz
                            (yemek talep edilirse ücretin %40'ı). <strong>6-11 yaş</strong> için yatak verilir ve ücretin %60'ı alınır.
                        </p>
                    </div>

                    <div class="mt-5 space-y-4">
                        <template x-for="(guest, index) in guests" :key="guest.uid">
                            <div class="rounded-xl2 border border-stone-200/90 bg-white/70 p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-stone-500" x-text="index === 0 ? 'Başvuru sahibi' : (index + 1) + '. kişi'"></p>
                                    <button type="button" x-show="index > 0" @click="removeGuest(index)"
                                            class="text-xs font-semibold text-red-600 hover:text-red-700">Kaldır</button>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="field-label">Ad soyad</label>
                                        <input type="text" x-model="guest.full_name" :name="'guests['+index+'][full_name]'" required class="field-input">
                                    </div>
                                    <div>
                                        <label class="field-label">TC kimlik no</label>
                                        <input type="text" inputmode="numeric" maxlength="11" x-model="guest.tc_no" :name="'guests['+index+'][tc_no]'" required class="field-input">
                                    </div>
                                    <div>
                                        <label class="field-label">Doğum tarihi</label>
                                        <input type="date" x-model="guest.birth_date" @change="refreshQuote()" :name="'guests['+index+'][birth_date]'" required class="field-input">
                                        <p class="field-hint" x-text="ageLabel(guest)"></p>
                                    </div>
                                    <div>
                                        <label class="field-label">Yakınlık</label>
                                        <select x-model="guest.relation" :name="'guests['+index+'][relation]'" required class="field-input">
                                            @foreach ($relations as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="field-label">Müşteri grubu</label>
                                        <select x-model.number="guest.customer_group_id" @change="refreshQuote()" :name="'guests['+index+'][customer_group_id]'" required class="field-input">
                                            @foreach ($groups as $group)
                                                <option value="{{ $group->id }}">{{ $group->name }} — {{ $group->description }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="field-label">Kimlik belgesi <span class="text-red-500">*</span></label>
                                        <input type="file" :name="'guests['+index+'][document]'" accept=".jpg,.jpeg,.png,.pdf" required
                                               class="field-input !py-2 file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                                        <p class="field-hint">JPG, PNG veya PDF · en fazla 5 MB. Belgeler yalnızca yetkili personelce görüntülenir.</p>
                                    </div>
                                    <template x-if="isInfant(guest)">
                                        <label class="sm:col-span-2 flex cursor-pointer items-center gap-2.5 rounded-lg bg-sand-50 px-3 py-2.5">
                                            <input type="checkbox" x-model="guest.wants_meal" @change="refreshQuote()" :name="'guests['+index+'][wants_meal]'" value="1"
                                                   class="rounded border-stone-300 text-teal-600 focus:ring-teal-500">
                                            <span class="text-xs text-navy-800">Bu çocuk için yemek servisi talep ediyorum (günlük ücretin %40'ı alınır)</span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    <button type="button" @click="addGuest()" x-show="guests.length < capacity"
                            class="btn-secondary mt-4 w-full border-dashed">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        Kişi ekle
                    </button>

                    <div class="mt-5">
                        <label class="field-label">Başvuru notu <span class="font-normal text-stone-400">(opsiyonel)</span></label>
                        <textarea x-model="note" rows="2" class="field-input" placeholder="Varsa özel talebinizi belirtin"></textarea>
                    </div>
                </div>

                {{-- ---------------------------------------------------------- --}}
                {{-- Adım 5 — Fiyat ve peşinat --}}
                {{-- ---------------------------------------------------------- --}}
                <div x-show="step === 5" x-cloak>
                    <h2 class="font-display text-xl font-semibold text-navy-900">Ücret dökümü ve peşinat</h2>
                    <p class="mt-1 text-sm text-stone-500">Tutar, müracaat tarihinizde geçerli ücret tablosuna göre hesaplanmıştır.</p>

                    <div x-show="quoteLoading" class="mt-6 empty-state !py-10">
                        <p class="text-sm text-stone-400">Ücret hesaplanıyor…</p>
                    </div>

                    <div x-show="quoteError" x-cloak class="mt-6 alert-soft border-red-200 bg-red-50 text-red-700 ring-red-200">
                        <p x-text="quoteError"></p>
                    </div>

                    <template x-if="quote && !quoteLoading">
                        <div class="mt-6 space-y-5">
                            {{-- Devre bazlı döküm --}}
                            <template x-for="seg in quote.segments" :key="seg.index">
                                <div class="overflow-hidden rounded-xl border border-stone-200/80">
                                    <div class="flex flex-wrap items-center justify-between gap-2 bg-sand-50 px-4 py-3">
                                        <div>
                                            <p class="text-sm font-semibold text-navy-900" x-text="seg.period_label + ' · ' + seg.nights + ' gün'"></p>
                                            <p class="text-[11px] text-stone-500" x-text="seg.date_range"></p>
                                        </div>
                                        <span class="text-[11px] text-stone-500" x-text="seg.tariff_name"></span>
                                    </div>
                                    <div class="divide-y divide-stone-100">
                                        <template x-for="line in seg.lines" :key="line.guest_index">
                                            <div class="flex items-center justify-between px-4 py-2.5 text-sm">
                                                <span class="text-stone-600">
                                                    <span x-text="line.name || ('Kişi ' + (line.guest_index + 1))"></span>
                                                    <span class="ml-1 text-[11px] text-stone-400" x-text="'(' + ageCategoryLabel(line.age_category) + ')'"></span>
                                                </span>
                                                <span class="font-medium text-navy-900" x-text="money(line.unit_price) + ' / gün'"></span>
                                            </div>
                                        </template>
                                        <div x-show="seg.minimum_applied" x-cloak class="flex items-center justify-between bg-amber-50/60 px-4 py-2.5 text-sm">
                                            <span class="text-amber-800">Villa asgari günlük tutarı uygulandı</span>
                                            <span class="font-medium text-amber-900" x-text="money(seg.min_daily_total) + ' / gün'"></span>
                                        </div>
                                        <div x-show="seg.empty_bed_total > 0" x-cloak class="flex items-center justify-between px-4 py-2.5 text-sm">
                                            <span class="text-stone-600" x-text="seg.empty_bed_count + ' boş yatak × ' + money(seg.empty_bed_fee_per_day) + ' × ' + seg.nights + ' gün'"></span>
                                            <span class="font-medium text-navy-900" x-text="money(seg.empty_bed_total)"></span>
                                        </div>
                                        <div class="flex items-center justify-between px-4 py-2.5 text-sm">
                                            <span class="font-semibold text-navy-800">Devre tutarı</span>
                                            <span class="font-semibold text-navy-900" x-text="money(seg.subtotal + seg.empty_bed_total)"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            {{-- Toplam --}}
                            <div class="overflow-hidden rounded-xl2 border border-stone-200/80">
                                <div x-show="quote.surcharge_per_person_day > 0" x-cloak class="flex justify-between border-b border-stone-100 px-4 py-3 text-sm">
                                    <span class="text-stone-500">Müracaat tarihi farkı (kişi/gün)</span>
                                    <span class="font-medium text-navy-900" x-text="money(quote.surcharge_per_person_day)"></span>
                                </div>
                                <div class="flex justify-between border-b border-stone-100 px-4 py-3 text-sm">
                                    <span class="text-stone-500">Konaklama</span>
                                    <span class="font-medium text-navy-900" x-text="money(quote.accommodation_total)"></span>
                                </div>
                                <div x-show="quote.empty_bed_total > 0" x-cloak class="flex justify-between border-b border-stone-100 px-4 py-3 text-sm">
                                    <span class="text-stone-500">Boş yatak ücreti</span>
                                    <span class="font-medium text-navy-900" x-text="money(quote.empty_bed_total)"></span>
                                </div>
                                <div class="flex items-center justify-between bg-navy-900 px-4 py-4 text-white">
                                    <span class="font-semibold">Toplam tutar</span>
                                    <span class="font-display text-2xl font-semibold" x-text="money(quote.total)"></span>
                                </div>
                                <div class="flex justify-between bg-teal-50 px-4 py-3.5 text-sm">
                                    <span class="font-semibold text-teal-900">Şimdi ödenecek peşinat</span>
                                    <span class="font-display text-lg font-semibold text-teal-800" x-text="money(quote.deposit_amount)"></span>
                                </div>
                                <div class="flex justify-between px-4 py-3 text-sm">
                                    <span class="text-stone-500">Yer tahsisinden sonra ödenecek bakiye</span>
                                    <span class="font-medium text-navy-900" x-text="money(quote.total - quote.deposit_amount)"></span>
                                </div>
                            </div>

                            <p class="field-hint">
                                Müracaat edilmesi ve peşinat yatırılması yer tahsisi yapılacağı anlamına gelmez. Yer tahsisi
                                yapılamayan başvuruların peşinatı faizsiz olarak iade edilir. Yönetim, oda tipini ve tutarı
                                değerlendirme sırasında güncelleyebilir.
                            </p>

                            {{-- Peşinat ödeme yöntemi --}}
                            <div>
                                <p class="field-label">Peşinatı nasıl ödemek istersiniz?</p>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <button type="button" @click="depositMethod = 'bank_transfer'" class="choice-tile"
                                            :class="depositMethod === 'bank_transfer' ? 'choice-tile-active' : 'choice-tile-idle'">
                                        <span class="font-semibold text-navy-900">Havale / EFT</span>
                                        <span class="text-xs text-stone-500">Dernek hesabına yatırıp dekontu yükleyin.</span>
                                    </button>
                                    <button type="button" @click="depositMethod = 'card'" class="choice-tile"
                                            :class="depositMethod === 'card' ? 'choice-tile-active' : 'choice-tile-idle'">
                                        <span class="font-semibold text-navy-900">Kredi / Banka kartı</span>
                                        <span class="text-xs text-stone-500">Sanal POS üzerinden güvenli ödeme.</span>
                                    </button>
                                </div>
                            </div>

                            <div x-show="depositMethod === 'bank_transfer'" x-cloak class="space-y-4">
                                <div class="overflow-hidden rounded-xl border border-stone-200/80">
                                    <p class="bg-sand-50 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-stone-500">Dernek banka hesapları</p>
                                    <div class="divide-y divide-stone-100">
                                        @foreach ($bankAccounts as $account)
                                            <div class="px-4 py-2.5">
                                                <p class="text-sm font-medium text-navy-900">{{ $account['bank'] }}</p>
                                                <p class="text-[11px] text-stone-500">{{ $account['branch'] ?? '' }}</p>
                                                <p class="mt-0.5 font-mono text-xs text-navy-800">{{ $account['iban'] }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    <label class="field-label">Banka dekontu <span class="text-red-500">*</span></label>
                                    <input type="file" name="deposit_receipt" accept=".jpg,.jpeg,.png,.pdf"
                                           :required="depositMethod === 'bank_transfer'"
                                           class="field-input !py-2 file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                                    <p class="field-hint">Dekontunuzu 1 yıl süreyle saklamanız gerekir.</p>
                                </div>
                            </div>

                            <div x-show="depositMethod === 'card'" x-cloak class="alert-soft border-teal-100 bg-teal-50/70 text-teal-900 ring-teal-100">
                                <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                                <p class="text-xs leading-relaxed">Başvurunuzu gönderdikten sonra bankanın güvenli 3D Secure sayfasına yönlendirileceksiniz. Kart bilgileriniz Dernek sistemlerinde saklanmaz.</p>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- ---------------------------------------------------------- --}}
                {{-- Adım 6 — Özet --}}
                {{-- ---------------------------------------------------------- --}}
                <div x-show="step === 6" x-cloak>
                    <h2 class="font-display text-xl font-semibold text-navy-900">Başvuru özeti</h2>
                    <p class="mt-1 text-sm text-stone-500">Bilgilerinizi kontrol edip müracaatınızı gönderin.</p>

                    <div class="mt-6 overflow-hidden rounded-xl border border-stone-200/80">
                        <div class="flex justify-between px-4 py-3 text-sm"><span class="text-stone-500">Tesis</span><span class="font-medium text-navy-900" x-text="selectedFacility?.name"></span></div>
                        <div class="flex justify-between border-t border-stone-100 px-4 py-3 text-sm"><span class="text-stone-500">Devre</span><span class="text-right font-medium text-navy-900" x-text="periodSummary"></span></div>
                        <div class="flex justify-between border-t border-stone-100 px-4 py-3 text-sm"><span class="text-stone-500">Oda tipi</span><span class="font-medium text-navy-900" x-text="selectedRoomType?.name"></span></div>
                        <div class="flex justify-between border-t border-stone-100 px-4 py-3 text-sm"><span class="text-stone-500">Kişi sayısı</span><span class="font-medium text-navy-900" x-text="guests.length"></span></div>
                        <div class="flex justify-between border-t border-stone-100 px-4 py-3 text-sm"><span class="text-stone-500">Konaklama</span><span class="font-medium text-navy-900" x-text="(quote?.nights ?? 0) + ' gün'"></span></div>
                        <div class="flex justify-between border-t border-stone-100 px-4 py-3 text-sm"><span class="text-stone-500">Peşinat ödemesi</span><span class="font-medium text-navy-900" x-text="depositMethod === 'card' ? 'Kredi / banka kartı' : 'Havale / EFT'"></span></div>
                        <div class="flex justify-between border-t border-stone-100 bg-sand-50 px-4 py-4"><span class="font-semibold text-navy-900">Toplam tutar</span><span class="font-display text-xl font-semibold text-teal-700" x-text="money(quote?.total ?? 0)"></span></div>
                        <div class="flex justify-between border-t border-stone-100 px-4 py-3 text-sm"><span class="text-stone-500">Şimdi ödenecek peşinat</span><span class="font-semibold text-navy-900" x-text="money(quote?.deposit_amount ?? 0)"></span></div>
                    </div>

                    <div class="mt-5 alert-soft border-teal-100 bg-teal-50/70 text-teal-900 ring-teal-100">
                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m6 2.25a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        <p class="text-xs leading-relaxed">
                            Başvurunuz Yönetim tarafından değerlendirilecektir. Yer tahsisi yapılması halinde oda tipi ve
                            tutar güncellenebilir; bakiyeyi tahsis bildiriminden itibaren 15 gün içinde ödemeniz gerekir.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Gezinme --}}
            <div class="mt-6 flex items-center justify-between gap-3">
                <button type="button" @click="back()" x-show="step > 1" x-cloak class="btn-secondary">Geri</button>
                <span x-show="step === 1"></span>

                <button type="button" @click="next()" x-show="step < 6" :disabled="!canProceed" class="btn-primary min-w-[7.5rem]">İleri</button>
                <button type="submit" x-show="step === 6" x-cloak :disabled="submitting || !quote" class="btn-accent min-w-[12rem]">
                    <span x-show="!submitting">Müracaatı Gönder</span>
                    <span x-show="submitting" x-cloak>Gönderiliyor…</span>
                </button>
            </div>
        </form>
    </div>

    <script>
        function applicationWizard() {
            const facilities = @js($facilities);
            const defaultGroupId = @js(auth()->user()->customer_group_id);
            const quoteUrl = @js(route('customer.reservations.quote'));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="_token"]').value;

            let uid = 0;
            const newGuest = (overrides = {}) => ({
                uid: ++uid,
                full_name: '',
                tc_no: '',
                birth_date: '',
                relation: 'self',
                customer_group_id: defaultGroupId,
                wants_meal: false,
                ...overrides,
            });

            return {
                step: 1,
                submitting: false,
                stepLabels: ['Tesis', 'Devre', 'Oda tipi', 'Kişiler', 'Ücret & peşinat', 'Özet'],

                facilities,
                facilityId: null,
                periodId: null,
                secondPeriodId: null,
                roomTypeId: null,
                guests: [newGuest({ full_name: @js(auth()->user()->name), tc_no: @js(auth()->user()->tc_no) })],
                groundFloorRequest: false,
                groundFloorNote: '',
                note: '',
                depositMethod: 'bank_transfer',

                quote: null,
                quoteLoading: false,
                quoteError: null,

                // ----- türetilmiş değerler -----
                get selectedFacility() {
                    return this.facilities.find(f => f.id === this.facilityId) ?? null;
                },
                get periods() {
                    return this.selectedFacility?.periods ?? [];
                },
                get roomTypes() {
                    return this.selectedFacility?.room_types ?? [];
                },
                get selectedPeriod() {
                    return this.periods.find(p => p.id === this.periodId) ?? null;
                },
                get selectedRoomType() {
                    return this.roomTypes.find(r => r.id === this.roomTypeId) ?? null;
                },
                get capacity() {
                    return this.selectedRoomType?.capacity ?? 1;
                },
                get periodSummary() {
                    if (!this.selectedPeriod) return '';
                    if (!this.secondPeriodId) return this.selectedPeriod.label + ' · ' + this.selectedPeriod.date_range;
                    const second = this.periods.find(p => p.id === this.secondPeriodId);
                    return this.selectedPeriod.label + ' + ' + second.label + ' · ' +
                        this.selectedPeriod.date_range.split(' – ')[0] + ' – ' + second.date_range.split(' – ')[1];
                },
                get guestsComplete() {
                    return this.guests.length > 0 && this.guests.every(g =>
                        g.full_name.trim() && /^\d{11}$/.test(g.tc_no) && g.birth_date && g.customer_group_id);
                },
                get canProceed() {
                    if (this.step === 1) return !!this.facilityId;
                    if (this.step === 2) return !!this.periodId;
                    if (this.step === 3) return !!this.roomTypeId && (!this.groundFloorRequest || this.groundFloorNote.trim().length > 0);
                    if (this.step === 4) return this.guestsComplete && this.guests.length <= this.capacity;
                    if (this.step === 5) return !!this.quote && !this.quoteLoading;
                    return true;
                },

                // ----- seçim -----
                selectFacility(f) {
                    if (this.facilityId === f.id) return;
                    this.facilityId = f.id;
                    this.periodId = null;
                    this.secondPeriodId = null;
                    this.roomTypeId = null;
                    this.quote = null;
                },
                selectPeriod(p) {
                    this.periodId = p.id;
                    this.secondPeriodId = null;
                    this.quote = null;
                },
                toggleSecondPeriod() {
                    this.secondPeriodId = this.secondPeriodId ? null : this.selectedPeriod.combinable_with;
                    this.quote = null;
                },
                selectRoomType(rt) {
                    this.roomTypeId = rt.id;
                    if (this.guests.length > rt.capacity) {
                        this.guests = this.guests.slice(0, rt.capacity);
                    }
                    this.quote = null;
                },

                // ----- kişiler -----
                addGuest() {
                    if (this.guests.length >= this.capacity) return;
                    this.guests.push(newGuest({ relation: 'spouse' }));
                },
                removeGuest(index) {
                    this.guests.splice(index, 1);
                    this.refreshQuote();
                },
                ageAt(birthDate, reference) {
                    if (!birthDate || !reference) return null;
                    const b = new Date(birthDate), r = new Date(reference);
                    let age = r.getFullYear() - b.getFullYear();
                    const m = r.getMonth() - b.getMonth();
                    if (m < 0 || (m === 0 && r.getDate() < b.getDate())) age--;
                    return age;
                },
                isInfant(guest) {
                    const age = this.ageAt(guest.birth_date, this.selectedPeriod?.start_date);
                    return age !== null && age < 6;
                },
                ageLabel(guest) {
                    const age = this.ageAt(guest.birth_date, this.selectedPeriod?.start_date);
                    if (age === null) return 'Devre başlangıcındaki yaşa göre ücretlendirilir.';
                    if (age < 0) return 'Doğum tarihi devre başlangıcından sonra olamaz.';
                    if (age < 6) return `Devre başında ${age} yaşında — 0-5 yaş grubu, yatak ücreti alınmaz.`;
                    if (age < 12) return `Devre başında ${age} yaşında — 6-11 yaş grubu, ücretin %60'ı alınır.`;
                    return `Devre başında ${age} yaşında — tam ücret.`;
                },
                ageCategoryLabel(category) {
                    return { child_0_5: '0-5 yaş', child_6_11: '6-11 yaş', adult: '12 yaş üstü' }[category] ?? category;
                },

                // ----- fiyat -----
                async refreshQuote() {
                    if (!this.roomTypeId || !this.periodId || !this.guestsComplete) return;

                    this.quoteLoading = true;
                    this.quoteError = null;

                    try {
                        const response = await fetch(quoteUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            },
                            body: JSON.stringify({
                                room_type_id: this.roomTypeId,
                                period_id: this.periodId,
                                second_period_id: this.secondPeriodId,
                                guests: this.guests.map(g => ({
                                    full_name: g.full_name,
                                    customer_group_id: g.customer_group_id,
                                    birth_date: g.birth_date,
                                    wants_meal: g.wants_meal,
                                })),
                            }),
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            this.quote = null;
                            this.quoteError = data.error ?? 'Ücret hesaplanamadı. Seçimlerinizi kontrol edin.';
                            return;
                        }

                        this.quote = data;
                    } catch (e) {
                        this.quote = null;
                        this.quoteError = 'Ücret hesaplanırken bağlantı hatası oluştu.';
                    } finally {
                        this.quoteLoading = false;
                    }
                },

                money(value) {
                    return new Intl.NumberFormat('tr-TR', {
                        style: 'currency', currency: 'TRY',
                        minimumFractionDigits: Number.isInteger(value) ? 0 : 2,
                        maximumFractionDigits: 2,
                    }).format(value ?? 0);
                },

                // ----- gezinme -----
                next() {
                    if (!this.canProceed) return;
                    if (this.step === 4) this.refreshQuote();
                    this.step++;
                },
                back() {
                    this.step--;
                },
            };
        }
    </script>
</x-layouts.customer>
