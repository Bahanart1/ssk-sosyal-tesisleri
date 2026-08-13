<x-layouts.admin :title="'Düzenle · ' . $reservation->code">

    <div x-data="{ removed: [], confirmApprove: false }" class="mx-auto max-w-5xl">
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

        <form id="reservation-edit" method="POST" action="{{ route('admin.reservations.update', $reservation) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="action" x-ref="action" value="save">

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">

                    {{-- Konaklama --}}
                    <div class="surface overflow-hidden">
                        <div class="border-b border-stone-100/80 px-6 py-4">
                            <h2 class="font-display text-lg font-semibold text-navy-900">Konaklama</h2>
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
                                <label class="field-label">İkinci devre <span class="font-normal text-stone-400">(opsiyonel)</span></label>
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
                    <div class="surface overflow-hidden">
                        <div class="border-b border-stone-100/80 px-6 py-4">
                            <h2 class="font-display text-lg font-semibold text-navy-900">Konaklayacak kişiler</h2>
                            <p class="text-xs text-stone-500">Grup ve doğum tarihi değişiklikleri ücreti doğrudan etkiler.</p>
                        </div>

                        <div class="divide-y divide-stone-100">
                            @foreach ($reservation->guests as $guest)
                                <div class="p-5" :class="removed.includes({{ $guest->id }}) ? 'opacity-40' : ''">
                                    <div class="mb-3 flex items-center justify-between">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">
                                            {{ $loop->first ? 'Başvuru sahibi' : $loop->iteration . '. kişi' }}
                                        </p>
                                        <div class="flex items-center gap-3">
                                            @if ($guest->id_document_path)
                                                <a href="{{ route('documents.identity', $guest) }}" target="_blank" rel="noopener"
                                                   class="text-xs font-semibold text-teal-700 hover:text-teal-800">Kimlik belgesi</a>
                                            @else
                                                <span class="badge-red !py-0.5 !text-[10px]">Belge eksik</span>
                                            @endif
                                            @unless ($loop->first)
                                                <button type="button"
                                                        @click="removed.includes({{ $guest->id }}) ? removed = removed.filter(i => i !== {{ $guest->id }}) : removed.push({{ $guest->id }})"
                                                        class="text-xs font-semibold"
                                                        :class="removed.includes({{ $guest->id }}) ? 'text-teal-700' : 'text-red-600'"
                                                        x-text="removed.includes({{ $guest->id }}) ? 'Geri al' : 'Listeden çıkar'"></button>
                                            @endunless
                                        </div>
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label class="field-label">Ad soyad</label>
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
                                        <label class="sm:col-span-2 flex cursor-pointer items-center gap-2.5 rounded-lg bg-sand-50 px-3 py-2.5">
                                            <input type="hidden" name="guests[{{ $guest->id }}][wants_meal]" value="0" :disabled="removed.includes({{ $guest->id }})">
                                            <input type="checkbox" name="guests[{{ $guest->id }}][wants_meal]" value="1" :disabled="removed.includes({{ $guest->id }})"
                                                   @checked(old("guests.{$guest->id}.wants_meal", $guest->wants_meal))
                                                   class="rounded border-stone-300 text-teal-600 focus:ring-teal-500">
                                            <span class="text-xs text-navy-800">0-5 yaş grubu için yemek servisi talep edildi (ücretin %40'ı)</span>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Ücret düzeltmeleri --}}
                    <div class="surface overflow-hidden">
                        <div class="border-b border-stone-100/80 px-6 py-4">
                            <h2 class="font-display text-lg font-semibold text-navy-900">Ücret düzeltmeleri</h2>
                        </div>
                        <div class="grid gap-5 p-6 sm:grid-cols-2">
                            <div>
                                <label class="field-label">Boş yatak sayısı</label>
                                <input type="number" min="0" max="10" name="empty_bed_count"
                                       value="{{ old('empty_bed_count', $reservation->empty_bed_count) }}" class="field-input">
                                <p class="field-hint">Boş bırakılırsa oda kapasitesine göre otomatik hesaplanır.</p>
                            </div>
                            <div>
                                <label class="field-label">Müracaat farkı (kişi/gün)</label>
                                <input type="number" step="0.01" min="0" name="surcharge_per_person_day"
                                       value="{{ old('surcharge_per_person_day', (float) $reservation->surcharge_per_person_day) }}" class="field-input">
                                <p class="field-hint">Müracaat tarihine göre otomatik belirlenir; gerekirse değiştirin.</p>
                            </div>
                            <div>
                                <label class="field-label">Düzeltme tutarı (+/−)</label>
                                <input type="number" step="0.01" name="adjustment_amount"
                                       value="{{ old('adjustment_amount', (float) $reservation->adjustment_amount) }}" class="field-input">
                            </div>
                            <div>
                                <label class="field-label">Düzeltme açıklaması</label>
                                <input type="text" name="adjustment_note" maxlength="255"
                                       value="{{ old('adjustment_note', $reservation->adjustment_note) }}"
                                       placeholder="Örn. klima ücreti, indirim" class="field-input">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="field-label">Üyeye iletilecek not</label>
                                <textarea name="admin_note" rows="3" class="field-input"
                                          placeholder="Örn. talep ettiğiniz oda tipi dolu olduğundan 2 kişilik odaya yerleştirildiniz.">{{ old('admin_note', $reservation->admin_note) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Yan panel: mevcut hesap --}}
                <div class="space-y-6">
                    <div class="surface overflow-hidden lg:sticky lg:top-6">
                        <div class="border-b border-stone-100/80 px-5 py-3.5">
                            <h2 class="font-display text-base font-semibold text-navy-900">Mevcut hesap</h2>
                            <p class="text-[11px] text-stone-500">Kaydettiğinizde yeniden hesaplanır.</p>
                        </div>

                        @if ($preview)
                            @foreach ($preview['segments'] as $segment)
                                <div class="border-b border-stone-100 px-5 py-3">
                                    <p class="text-xs font-semibold text-navy-900">{{ $segment['period_label'] }} · {{ $segment['nights'] }} gün</p>
                                    <p class="text-[11px] text-stone-500">{{ $segment['tariff_name'] }}</p>
                                    <div class="mt-2 space-y-1">
                                        @foreach ($segment['lines'] as $line)
                                            <div class="flex justify-between text-[11px]">
                                                <span class="truncate text-stone-500">{{ $line['name'] ?: 'Kişi' }}</span>
                                                <x-money :value="$line['unit_price']" zero="Ücretsiz" class="text-stone-700" />
                                            </div>
                                        @endforeach
                                        @if ($segment['minimum_applied'])
                                            <div class="flex justify-between text-[11px] text-amber-700">
                                                <span>Villa asgari günlük</span>
                                                <x-money :value="$segment['min_daily_total']" />
                                            </div>
                                        @endif
                                        @if ($segment['empty_bed_total'] > 0)
                                            <div class="flex justify-between text-[11px] text-stone-500">
                                                <span>{{ $segment['empty_bed_count'] }} boş yatak</span>
                                                <x-money :value="$segment['empty_bed_total']" />
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach

                            <div class="divide-y divide-stone-100 text-sm">
                                <div class="flex justify-between px-5 py-2.5"><span class="text-stone-500">Konaklama</span><x-money :value="$preview['accommodation_total']" class="font-medium text-navy-900" /></div>
                                @if ($preview['empty_bed_total'] > 0)
                                    <div class="flex justify-between px-5 py-2.5"><span class="text-stone-500">Boş yatak</span><x-money :value="$preview['empty_bed_total']" class="font-medium text-navy-900" /></div>
                                @endif
                                @if ($preview['adjustment_amount'] != 0)
                                    <div class="flex justify-between px-5 py-2.5"><span class="text-stone-500">Düzeltme</span><x-money :value="$preview['adjustment_amount']" class="font-medium text-navy-900" /></div>
                                @endif
                                <div class="flex items-center justify-between bg-navy-900 px-5 py-3.5 text-white">
                                    <span class="text-sm font-semibold">Toplam</span>
                                    <x-money :value="$preview['total']" class="font-display text-xl font-semibold" />
                                </div>
                                <div class="flex justify-between px-5 py-2.5"><span class="text-stone-500">Peşinat</span><x-money :value="$preview['deposit_amount']" class="font-medium text-navy-900" /></div>
                                <div class="flex justify-between px-5 py-2.5"><span class="text-stone-500">Tahsil edilen</span><x-money :value="$reservation->paidTotal()" class="font-medium text-navy-900" /></div>
                                <div class="flex justify-between px-5 py-2.5"><span class="font-semibold text-stone-600">Kalan bakiye</span><x-money :value="max(0, $preview['total'] - $reservation->paidTotal())" class="font-semibold text-navy-900" /></div>
                            </div>
                        @else
                            <p class="px-5 py-8 text-center text-sm text-stone-400">Hesap yapılamadı. Devre ve tarife eşleşmesini kontrol edin.</p>
                        @endif

                        <div class="space-y-2 border-t border-stone-100 p-5">
                            <button type="submit" @click="$refs.action.value = 'save'" class="btn-secondary w-full">
                                Kaydet ve Yeniden Hesapla
                            </button>
                            <button type="button" @click="confirmApprove = true" class="btn-accent w-full py-2.5">
                                Onayla ve Ödemeye Gönder
                            </button>
                        </div>
                    </div>

                    <div class="alert-soft border-teal-100 bg-teal-50/70 text-teal-900 ring-teal-100">
                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                        <p class="text-xs leading-relaxed">
                            Onayladığınızda bakiye son ödeme tarihi, tahsis bildiriminden itibaren 15 gün olarak
                            belirlenir; devre başlangıcına 15 günden az kalmışsa devre başlangıcı esas alınır.
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
                    <h3 class="font-display text-lg font-semibold text-navy-900">Yer tahsisini onayla</h3>
                    <p class="mt-2 text-sm text-stone-500">
                        Değişiklikler kaydedilecek, ücret yeniden hesaplanacak ve başvuru sahibine bakiye ödemesi
                        için açılacak. Devam etmek istiyor musunuz?
                    </p>
                    <div class="mt-5 flex gap-3">
                        <button type="button" @click="confirmApprove = false" class="btn-secondary flex-1">Vazgeç</button>
                        <button type="submit" form="reservation-edit" @click="$refs.action.value = 'approve'"
                                class="btn-accent flex-1">Onayla ve Gönder</button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-layouts.admin>
