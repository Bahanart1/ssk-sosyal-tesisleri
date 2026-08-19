<x-layouts.admin :title="'Düzenle · ' . $reservation->code">

    <div x-data="{
            removed: [], confirmApprove: false, yeniler: [], sayac: 0, uyari: '',
            mevcutKisi: {{ $reservation->guests->count() }},
            get kalanKisi() { return this.mevcutKisi - this.removed.length + this.yeniler.length },
            cikar(id) {
                if (this.removed.includes(id)) { this.removed = this.removed.filter(i => i !== id); this.uyari = ''; return }
                if (this.kalanKisi <= 1) { this.uyari = 'Rezervasyonda en az bir kişi kalmalıdır.'; return }
                this.uyari = ''; this.removed.push(id)
            }
         }" class="mx-auto max-w-5xl">
        <a href="{{ route('admin.reservations.show', $reservation) }}" class="back-link">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Başvuru detayı
        </a>

        <div class="mt-4 mb-6">
            <p class="section-label">Yer tahsisi</p>
            <h1 class="page-title mt-1">{{ $reservation->code }} · {{ $reservation->user->name }}</h1>
            <p class="page-subtitle">
                Oda tipini, devreyi, kişi listesini ve tutarı değiştirebilirsiniz. Değişiklikler kaydedildiğinde
                ücret yeniden hesaplanır; onayladığınızda üyeye bakiye ödemesi için açılır.
            </p>
        </div>

        @if ($errors->any())
            <div class="alert-soft mb-6 border-red-200 bg-red-50 text-red-700 ring-red-200">
                <div>
                    <p class="font-semibold">Kaydedilemedi</p>
                    <ul class="mt-1 list-disc space-y-0.5 pl-4">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form id="reservation-edit" method="POST" action="{{ route('admin.reservations.update', $reservation) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <input type="hidden" name="action" x-ref="action" value="save">

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">

                    {{-- Konaklama --}}
                    <div class="surface overflow-hidden">
                        <div class="border-b border-line px-6 py-4">
                            <h2 class="font-display text-lg font-semibold text-ink">Konaklama</h2>
                        </div>
                        <div class="grid gap-5 p-6 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="field-label">Oda tipi</label>
                                <select name="room_type_id" class="field-input">
                                    @foreach ($roomTypes as $roomType)
                                        <option value="{{ $roomType->id }}" @selected(old('room_type_id', $reservation->room_type_id) == $roomType->id)>
                                            {{ $roomType->name }} — {{ $roomType->bed_count }} yatak{{ $roomType->is_ground_floor ? ' · zemin kat (%10 indirim)' : '' }}{{ $roomType->kind === 'villa' ? ' · villa' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Devre</label>
                                <select name="period_id" class="field-input">
                                    @foreach ($periods as $period)
                                        <option value="{{ $period->id }}" @selected(old('period_id', $reservation->period_id) == $period->id)>
                                            {{ $period->label() }} — {{ $period->dateRange() }}{{ $period->is_discounted ? ' (indirimli)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="field-label">İkinci devre <span class="font-normal text-ink-subtle">(opsiyonel)</span></label>
                                <select name="second_period_id" class="field-input">
                                    <option value="">Yok</option>
                                    @foreach ($periods as $period)
                                        <option value="{{ $period->id }}" @selected(old('second_period_id', $reservation->second_period_id) == $period->id)>
                                            {{ $period->label() }} — {{ $period->dateRange() }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="field-hint">Yalnızca ardışık ve birleşebilen devreler seçilebilir.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Kişiler --}}
                    <div id="kisiler" class="surface overflow-hidden scroll-mt-6">
                        <div class="border-b border-line px-6 py-4">
                            <h2 class="font-display text-lg font-semibold text-ink">Konaklayacak kişiler</h2>
                            <p class="text-xs text-ink-muted">Grup ve doğum tarihi değişiklikleri ücreti doğrudan etkiler.</p>
                        </div>

                        <div class="divide-y divide-line">
                            @foreach ($reservation->guests as $guest)
                                <div class="p-5" :class="removed.includes({{ $guest->id }}) ? 'opacity-40' : ''">
                                    <div class="mb-3 flex items-center justify-between">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">
                                            {{ $loop->first ? 'Başvuru sahibi' : $loop->iteration . '. kişi' }}
                                            <span x-show="removed.includes({{ $guest->id }})" x-cloak
                                                  class="ml-1 font-normal normal-case" style="color: var(--status-danger)">· çıkarılacak</span>
                                        </p>
                                        <div class="flex items-center gap-3">
                                            @if ($guest->id_document_path)
                                                <a href="{{ route('documents.identity', $guest) }}" target="_blank" rel="noopener"
                                                   class="text-xs font-semibold text-accent-700 dark:text-accent-300 hover:text-accent-800 dark:hover:text-accent-200">Kimlik</a>
                                            @endif
                                            @if ($guest->civil_registry_path)
                                                <a href="{{ route('documents.civil-registry', $guest) }}" target="_blank" rel="noopener"
                                                   class="text-xs font-semibold text-accent-700 dark:text-accent-300 hover:text-accent-800 dark:hover:text-accent-200">Nüfus kaydı</a>
                                            @endif
                                            <button type="button" @click="cikar({{ $guest->id }})"
                                                    class="text-xs font-semibold"
                                                    :class="removed.includes({{ $guest->id }}) ? 'text-accent-700 dark:text-accent-300' : 'text-red-600'"
                                                    x-text="removed.includes({{ $guest->id }}) ? 'Geri al' : 'Listeden çıkar'"></button>
                                        </div>
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label class="field-label">Ad soyad</label>
                                            <input type="hidden" name="guests[{{ $guest->id }}][id]" value="{{ $guest->id }}" :disabled="removed.includes({{ $guest->id }})">
                                            <input type="text" name="guests[{{ $guest->id }}][full_name]" :disabled="removed.includes({{ $guest->id }})"
                                                   value="{{ old("guests.{$guest->id}.full_name", $guest->full_name) }}" class="field-input">
                                        </div>
                                        <div>
                                            <label class="field-label">TC kimlik no</label>
                                            <input type="text" maxlength="11" name="guests[{{ $guest->id }}][tc_no]" :disabled="removed.includes({{ $guest->id }})"
                                                   value="{{ old("guests.{$guest->id}.tc_no", $guest->tc_no) }}" class="field-input font-mono">
                                        </div>
                                        <div>
                                            <label class="field-label">Doğum tarihi</label>
                                            <input type="date" name="guests[{{ $guest->id }}][birth_date]" :disabled="removed.includes({{ $guest->id }})"
                                                   value="{{ old("guests.{$guest->id}.birth_date", $guest->birth_date->toDateString()) }}" class="field-input">
                                            <p class="field-hint">Şu anki yaş grubu: {{ $guest->ageCategoryLabel() }}</p>
                                        </div>
                                        <div>
                                            <label class="field-label">Yakınlık</label>
                                            <select name="guests[{{ $guest->id }}][relation]" :disabled="removed.includes({{ $guest->id }})" class="field-input">
                                                @foreach ($relations as $value => $label)
                                                    <option value="{{ $value }}" @selected(old("guests.{$guest->id}.relation", $guest->relation) === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="field-label">Müşteri grubu</label>
                                            <select name="guests[{{ $guest->id }}][customer_group_id]" :disabled="removed.includes({{ $guest->id }})" class="field-input">
                                                @foreach ($groups as $group)
                                                    <option value="{{ $group->id }}" @selected(old("guests.{$guest->id}.customer_group_id", $guest->customer_group_id) == $group->id)>
                                                        {{ $group->name }} — {{ $group->description }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @if (! $guest->id_document_path || ! $guest->civil_registry_path)
                                            <div class="grid gap-4 sm:col-span-2 sm:grid-cols-2">
                                                @unless ($guest->id_document_path)
                                                    <div>
                                                        <label class="field-label">
                                                            Kimlik belgesi <span class="font-normal" style="color: var(--status-warn)">(eksik)</span>
                                                        </label>
                                                        <input type="file" name="guests[{{ $guest->id }}][document]" accept=".jpg,.jpeg,.png,.pdf"
                                                               :disabled="removed.includes({{ $guest->id }})"
                                                               class="field-input !py-2 file:mr-3 file:rounded-lg file:border-0 file:bg-accent-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                                                    </div>
                                                @endunless
                                                @unless ($guest->civil_registry_path)
                                                    <div>
                                                        <label class="field-label">
                                                            Vukuatlı nüfus kaydı <span class="font-normal" style="color: var(--status-warn)">(eksik)</span>
                                                        </label>
                                                        <input type="file" name="guests[{{ $guest->id }}][civil_registry]" accept=".jpg,.jpeg,.png,.pdf"
                                                               :disabled="removed.includes({{ $guest->id }})"
                                                               class="field-input !py-2 file:mr-3 file:rounded-lg file:border-0 file:bg-accent-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                                                    </div>
                                                @endunless
                                            </div>
                                        @endif

                                        <label class="sm:col-span-2 flex cursor-pointer items-center gap-2.5 rounded-lg bg-surface-alt px-3 py-2.5">
                                            <input type="hidden" name="guests[{{ $guest->id }}][wants_meal]" value="0" :disabled="removed.includes({{ $guest->id }})">
                                            <input type="checkbox" name="guests[{{ $guest->id }}][wants_meal]" value="1" :disabled="removed.includes({{ $guest->id }})"
                                                   @checked(old("guests.{$guest->id}.wants_meal", $guest->wants_meal))
                                                   class="rounded border-line text-accent-600 dark:text-accent-400 focus:ring-accent-500">
                                            <span class="text-xs text-ink">0-5 yaş grubu için yemek servisi talep edildi (ücretin %40'ı)</span>
                                        </label>
                                    </div>
                                </div>
                            @endforeach

                            {{-- Yöneticinin sonradan eklediği kişiler --}}
                            <template x-for="y in yeniler" :key="y">
                                <div class="bg-accent-50/40 p-5 dark:bg-accent-900/10">
                                    <div class="mb-3 flex items-center justify-between">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-accent-700 dark:text-accent-300">Yeni kişi</p>
                                        <button type="button" @click="yeniler = yeniler.filter(i => i !== y)"
                                                class="text-xs font-medium text-red-600">Kaldır</button>
                                    </div>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label class="field-label">Ad soyad</label>
                                            <input type="text" :name="'guests[yeni-' + y + '][full_name]'" class="field-input">
                                        </div>
                                        <div>
                                            <label class="field-label">TC kimlik no</label>
                                            <input type="text" maxlength="11" inputmode="numeric" :name="'guests[yeni-' + y + '][tc_no]'" class="field-input">
                                        </div>
                                        <div>
                                            <label class="field-label">Doğum tarihi</label>
                                            <input type="date" :name="'guests[yeni-' + y + '][birth_date]'" class="field-input">
                                        </div>
                                        <div>
                                            <label class="field-label">Yakınlık</label>
                                            <select :name="'guests[yeni-' + y + '][relation]'" class="field-input">
                                                @foreach ($relations as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="field-label">Müşteri grubu</label>
                                            <select :name="'guests[yeni-' + y + '][customer_group_id]'" class="field-input">
                                                @foreach ($groups as $group)
                                                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label class="field-label">Kimlik belgesi</label>
                                            <input type="file" :name="'guests[yeni-' + y + '][document]'" accept=".jpg,.jpeg,.png,.pdf"
                                                   class="field-input !py-2 file:mr-3 file:rounded-lg file:border-0 file:bg-accent-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                                        </div>
                                        <div>
                                            <label class="field-label">Vukuatlı nüfus kaydı</label>
                                            <input type="file" :name="'guests[yeni-' + y + '][civil_registry]'" accept=".jpg,.jpeg,.png,.pdf"
                                                   class="field-input !py-2 file:mr-3 file:rounded-lg file:border-0 file:bg-accent-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                                        </div>
                                    </div>
                                    <p class="mt-2 text-[11px] text-ink-subtle">
                                        JPG, PNG veya PDF · en fazla 5 MB. Belgeler elden alındıysa boş bırakabilirsiniz;
                                        eksik olduğu sürece kişinin yanında uyarı görünür.
                                    </p>
                                </div>
                            </template>
                        </div>

                        <div class="border-t border-line p-5">
                            <button type="button" @click="yeniler.push(++sayac); uyari = ''" class="btn-secondary w-full border-dashed">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                Kişi ekle
                            </button>

                            <p x-show="uyari" x-cloak x-transition
                               class="mt-3 rounded-lg px-3 py-2 text-xs font-medium"
                               style="background: var(--c-surface-sunken); color: var(--status-warn)"
                               x-text="uyari"></p>

                            <p class="mt-2 text-[11px] text-ink-subtle">
                                <span x-text="kalanKisi"></span> kişi kaydedilecek.
                                Kapasiteyi aşarsanız oda tipini büyütün ya da ikinci oda tahsis edin.
                            </p>
                        </div>
                    </div>

                    {{--
                        Ücret sistem tarafından hesaplanır. Bu kart yalnızca istisnai
                        elle müdahale içindir ve varsayılan olarak kapalıdır.
                    --}}
                    <div class="surface overflow-hidden" x-data="{ acik: {{ $errors->hasAny(['empty_bed_count', 'surcharge_per_person_day', 'adjustment_amount']) ? 'true' : 'false' }} }">
                        <button type="button" @click="acik = !acik"
                                class="flex w-full items-center justify-between px-6 py-4 text-left">
                            <div>
                                <h2 class="font-display text-lg font-semibold text-ink">Elle ücret müdahalesi</h2>
                                <p class="text-xs text-ink-muted">Gerekmez: ücret kişi listesine göre kendiliğinden hesaplanır. Yalnızca istisnai durumda açın.</p>
                            </div>
                            <svg class="h-5 w-5 shrink-0 text-ink-subtle transition-transform" :class="acik ? 'rotate-180' : ''"
                                 fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                        </button>
                        <div x-show="acik" x-cloak class="grid gap-5 border-t border-line p-6 sm:grid-cols-2">
                            <div>
                                <label class="field-label">Boş yatak sayısı</label>
                                {{--
                                    Alan bilerek boş bırakılır. Eski değerle doldurulursa her kayıtta
                                    override olur ve kişi çıkarıldığında boş yatak ücreti hesaplanmaz.
                                --}}
                                <input type="number" min="0" max="10" name="empty_bed_count"
                                       value="{{ old('empty_bed_count') }}"
                                       placeholder="Otomatik ({{ (int) $reservation->empty_bed_count }})"
                                       class="field-input">
                                <p class="field-hint">
                                    Boş bırakın: kişi sayısına göre kendiliğinden hesaplanır.
                                    Kişi çıkardığınızda boş kalan yatakların ücreti buradan gelir.
                                    Yalnızca istisnai durumda elle yazın.
                                </p>
                            </div>
                            <div>
                                <label class="field-label">Müracaat farkı (kişi/gün)</label>
                                <input type="number" step="0.01" min="0" name="surcharge_per_person_day"
                                       value="{{ old('surcharge_per_person_day') }}"
                                       placeholder="Otomatik ({{ number_format((float) $reservation->surcharge_per_person_day, 2, ',', '.') }})"
                                       class="field-input">
                                <p class="field-hint">Boş bırakın; müracaat tarihine göre kendiliğinden belirlenir.</p>
                            </div>
                            <div>
                                <label class="field-label">Düzeltme tutarı (+/−)</label>
                                <input type="number" step="0.01" name="adjustment_amount"
                                       value="{{ old('adjustment_amount') }}"
                                       placeholder="{{ number_format((float) $reservation->adjustment_amount, 2, ',', '.') }}"
                                       class="field-input">
                            </div>
                            <div>
                                <label class="field-label">Düzeltme açıklaması</label>
                                <input type="text" name="adjustment_note" maxlength="255"
                                       value="{{ old('adjustment_note', $reservation->adjustment_note) }}"
                                       placeholder="Örn. klima ücreti, indirim" class="field-input">
                            </div>
                        </div>
                    </div>

                    {{-- Üyeye iletilecek not: üye bunu rezervasyon sayfasında görür --}}
                    <div class="surface overflow-hidden">
                        <div class="border-b border-line px-6 py-4">
                            <h2 class="font-display text-lg font-semibold text-ink">Üyeye iletilecek not</h2>
                            <p class="text-xs text-ink-muted">Üye bu notu ve ödeyeceği tutarı rezervasyon sayfasında görür.</p>
                        </div>
                        <div class="p-6">
                            <textarea name="admin_note" rows="3" class="field-input"
                                      placeholder="Örn. Talebiniz üzerine eşiniz rezervasyona eklendi; oluşan fark aşağıda belirtilmiştir.">{{ old('admin_note', $reservation->admin_note) }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Yan panel: mevcut hesap --}}
                <div class="space-y-6">
                    <div class="surface overflow-hidden lg:sticky lg:top-6">
                        <div class="border-b border-line px-5 py-3.5">
                            <h2 class="font-display text-base font-semibold text-ink">Mevcut hesap</h2>
                            <p class="text-[11px] text-ink-muted">Kaydettiğinizde yeniden hesaplanır.</p>
                        </div>

                        @if ($preview)
                            @foreach ($preview['segments'] as $segment)
                                <div class="border-b border-line px-5 py-3">
                                    <p class="text-xs font-semibold text-ink">{{ $segment['period_label'] }} · {{ $segment['nights'] }} gün</p>
                                    <p class="text-[11px] text-ink-muted">{{ $segment['tariff_name'] }}</p>
                                    <div class="mt-2 space-y-1">
                                        @foreach ($segment['lines'] as $line)
                                            <div class="flex justify-between text-[11px]">
                                                <span class="truncate text-ink-muted">{{ $line['name'] ?: 'Kişi' }}</span>
                                                <x-money :value="$line['unit_price']" zero="Ücretsiz" class="text-ink" />
                                            </div>
                                        @endforeach
                                        @if ($segment['minimum_applied'])
                                            <div class="flex justify-between text-[11px] text-amber-700">
                                                <span>Villa asgari günlük</span>
                                                <x-money :value="$segment['min_daily_total']" />
                                            </div>
                                        @endif
                                        @if ($segment['empty_bed_total'] > 0)
                                            <div class="flex justify-between text-[11px] text-ink-muted">
                                                <span>{{ $segment['empty_bed_count'] }} boş yatak</span>
                                                <x-money :value="$segment['empty_bed_total']" />
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach

                            <div class="divide-y divide-line text-sm">
                                <div class="flex justify-between px-5 py-2.5"><span class="text-ink-muted">Konaklama</span><x-money :value="$preview['accommodation_total']" class="font-medium text-ink" /></div>
                                @if ($preview['empty_bed_total'] > 0)
                                    <div class="flex justify-between px-5 py-2.5"><span class="text-ink-muted">Boş yatak</span><x-money :value="$preview['empty_bed_total']" class="font-medium text-ink" /></div>
                                @endif
                                @if ($preview['adjustment_amount'] != 0)
                                    <div class="flex justify-between px-5 py-2.5"><span class="text-ink-muted">Düzeltme</span><x-money :value="$preview['adjustment_amount']" class="font-medium text-ink" /></div>
                                @endif
                                <div class="flex items-center justify-between bg-chrome px-5 py-3.5 text-white">
                                    <span class="text-sm font-semibold">Toplam</span>
                                    <x-money :value="$preview['total']" class="font-display text-xl font-semibold" />
                                </div>
                                <div class="flex justify-between px-5 py-2.5"><span class="text-ink-muted">Peşinat</span><x-money :value="$preview['deposit_amount']" class="font-medium text-ink" /></div>
                                @php
                                    $tahsil = $reservation->paidTotal();
                                    $fark = round((float) $preview['total'] - $tahsil, 2);
                                @endphp

                                <div class="flex justify-between px-5 py-2.5">
                                    <span class="text-ink-muted">Tahsil edilen</span>
                                    <x-money :value="$tahsil" class="font-medium text-ink" />
                                </div>

                                {{-- Kişi eklenip çıkarıldığında ortaya çıkan fark --}}
                                @if ($fark > 0.009)
                                    <div class="flex justify-between px-5 py-3" style="background: var(--c-surface-sunken)">
                                        <span class="font-semibold" style="color: var(--status-warn)">Üyeden tahsil edilecek</span>
                                        <x-money :value="$fark" class="font-display text-lg font-semibold" style="color: var(--status-warn)" />
                                    </div>
                                @elseif ($fark < -0.009)
                                    <div class="flex justify-between px-5 py-3" style="background: var(--c-surface-sunken)">
                                        <span class="font-semibold" style="color: var(--status-good)">Üyeye iade edilecek</span>
                                        <x-money :value="abs($fark)" class="font-display text-lg font-semibold" style="color: var(--status-good)" />
                                    </div>
                                @else
                                    <div class="flex justify-between px-5 py-2.5">
                                        <span class="font-semibold text-ink-muted">Kalan bakiye</span>
                                        <x-money :value="0" class="font-semibold text-ink" />
                                    </div>
                                @endif
                            </div>
                        @else
                            <p class="px-5 py-8 text-center text-sm text-ink-subtle">Hesap yapılamadı. Devre ve tarife eşleşmesini kontrol edin.</p>
                        @endif

                        <div class="space-y-2 border-t border-line p-5">
                            <button type="submit" @click="$refs.action.value = 'save'" class="btn-secondary w-full">
                                Kaydet ve Yeniden Hesapla
                            </button>
                            <button type="button" @click="confirmApprove = true" class="btn-accent w-full py-2.5">
                                {{ $reservation->status === 'pending' ? 'Onayla ve Ödemeye Gönder' : 'Ödemeyi Üyeye Gönder' }}
                            </button>
                        </div>
                    </div>

                    <div class="alert-soft border-accent-200 dark:border-accent-800 bg-accent-50 dark:bg-accent-900/30 text-accent-900 dark:text-accent-100 ring-accent-200 dark:ring-accent-800">
                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-accent-600 dark:text-accent-400" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                        <p class="text-xs leading-relaxed">
                            Ücret, kişi listesine göre sistem tarafından hesaplanır.
                            {{ $reservation->status === 'pending'
                                ? 'Onayladığınızda üyeye ödeme açılır; kartla, havaleyle ya da tesiste ödeyebilir.'
                                : 'Gönderdiğinizde üye notunuzu ve ödeyeceği tutarı rezervasyon sayfasında görür.' }}
                        </p>
                    </div>
                </div>
            </div>

        </form>

        {{--
            Onay modalı body'ye taşınır: <main> üzerindeki giriş animasyonu bir transform
            oluşturduğundan, sayfa içinde bırakılan "fixed" bir katman ekrana değil main'e
            göre konumlanır. Gönderim düğmesi forma HTML "form" özniteliğiyle bağlanır.
        --}}
        <template x-teleport="body">
            <div x-show="confirmApprove" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="modal-scrim" @click="confirmApprove = false"></div>
                <div class="modal-panel" x-transition>
                    @if ($reservation->status === 'pending')
                        <h3 class="font-display text-lg font-semibold text-ink">Yer tahsisini onayla</h3>
                        <p class="mt-2 text-sm text-ink-muted">
                            Değişiklikler kaydedilecek, ücret sistem tarafından hesaplanacak ve üyeye
                            ödeme için açılacak. Devam etmek istiyor musunuz?
                        </p>
                    @else
                        <h3 class="font-display text-lg font-semibold text-ink">Ödemeyi üyeye gönder</h3>
                        <p class="mt-2 text-sm text-ink-muted">
                            Kişi değişiklikleri kaydedilecek, ücret sistem tarafından yeniden hesaplanacak.
                            Fark çıkarsa üyeye ödemesi için bildirilir; tutar düştüyse iade edilecek tutar
                            kaydedilir. Üye, notunuzu ve güncel tutarı rezervasyon sayfasında görür.
                        </p>
                    @endif
                    <div class="mt-5 flex gap-3">
                        <button type="button" @click="confirmApprove = false" class="btn-secondary flex-1">Vazgeç</button>
                        <button type="submit" form="reservation-edit"
                                @click="$refs.action.value = '{{ $reservation->status === 'pending' ? 'approve' : 'send_payment' }}'"
                                class="btn-accent flex-1">{{ $reservation->status === 'pending' ? 'Onayla ve Gönder' : 'Gönder' }}</button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-layouts.admin>
