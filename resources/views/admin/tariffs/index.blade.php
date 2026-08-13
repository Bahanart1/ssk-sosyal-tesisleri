<x-layouts.admin title="Tarifeler">

    <div class="mb-6">
        <p class="section-label">Yönetim</p>
        <h1 class="page-title mt-1">Ücret tarifeleri</h1>
        <p class="page-subtitle">
            Kişi başı günlük ücretler (vergiler dahil). <strong>Tablo 1</strong> oda ücretleri,
            <strong>Tablo 2</strong> Çolaklı villalarının yemeksiz ücretleridir. Boş yatak ücreti boş
            bırakılırsa "alınmaz" kabul edilir.
        </p>
    </div>

    <form method="GET" class="surface mb-6 flex items-end gap-3 p-4">
        <div>
            <label class="field-label">Yıl</label>
            <select name="year" class="field-input" onchange="this.form.submit()">
                @foreach ($years as $option)
                    <option value="{{ $option }}" @selected($year === (int) $option)>{{ $option }}</option>
                @endforeach
                @if (! $years->contains($year))
                    <option value="{{ $year }}" selected>{{ $year }}</option>
                @endif
            </select>
        </div>
        <noscript><button class="btn-primary">Göster</button></noscript>
    </form>

    @foreach ($facilities as $facility)
        <div class="mb-8">
            <h2 class="mb-3 font-display text-xl font-semibold text-ink">{{ $facility->name }}</h2>

            @if ($facility->tariffs->isEmpty())
                <div class="surface px-6 py-10 text-center text-sm text-ink-subtle">
                    {{ $year }} yılı için tarife tanımlanmamış.
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($facility->tariffs as $tariff)
                        <form method="POST" action="{{ route('admin.tariffs.update', $tariff) }}" class="surface overflow-hidden">
                            @csrf
                            @method('PUT')

                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-surface-alt px-5 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="badge-{{ $tariff->scope === 'villa' ? 'amber' : 'gray' }} !py-0.5 !text-[10px]">
                                        {{ $tariff->scope === 'villa' ? 'Tablo 2 · Villa' : 'Tablo 1 · Oda' }}
                                    </span>
                                    @if ($tariff->is_discounted)
                                        <span class="badge-teal !py-0.5 !text-[10px]">İndirimli</span>
                                    @endif
                                </div>
                                <button type="submit" class="btn-primary !px-3 !py-1.5 text-xs">Kaydet</button>
                            </div>

                            <div class="grid gap-4 border-b border-line p-5 sm:grid-cols-3">
                                <div class="sm:col-span-2">
                                    <label class="field-label">Tarife adı</label>
                                    <input type="text" name="name" value="{{ $tariff->name }}" required class="field-input">
                                </div>
                                <div>
                                    <label class="field-label">Boş yatak ücreti (günlük)</label>
                                    <input type="number" step="0.01" min="0" name="empty_bed_fee"
                                           value="{{ $tariff->empty_bed_fee }}" placeholder="Alınmaz" class="field-input">
                                </div>
                                <label class="flex cursor-pointer items-center gap-2 text-sm sm:col-span-3">
                                    <input type="hidden" name="is_discounted" value="0">
                                    <input type="checkbox" name="is_discounted" value="1" @checked($tariff->is_discounted)
                                           class="rounded border-line text-accent-600 dark:text-accent-400 focus:ring-accent-500">
                                    İndirimli devre tarifesi
                                </label>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Müşteri grubu</th>
                                            <th>12 yaş üstü (kişi/gün)</th>
                                            <th>6-11 yaş (kişi/gün)</th>
                                            <th>En düşük günlük tutar</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-line">
                                        @foreach ($groups as $group)
                                            @php $price = $tariff->prices->firstWhere('customer_group_id', $group->id); @endphp
                                            <tr>
                                                <td>
                                                    <p class="font-medium">{{ $group->name }}</p>
                                                    <p class="max-w-xs text-[11px] text-ink-muted">{{ $group->description }}</p>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" required
                                                           name="prices[{{ $group->id }}][adult_price]"
                                                           value="{{ $price?->adult_price }}" class="field-input !py-1.5 max-w-[9rem]">
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0"
                                                           name="prices[{{ $group->id }}][child_price]"
                                                           value="{{ $price?->child_price }}"
                                                           placeholder="%60 otomatik" class="field-input !py-1.5 max-w-[9rem]">
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0"
                                                           name="prices[{{ $group->id }}][min_daily_total]"
                                                           value="{{ $price?->min_daily_total }}"
                                                           placeholder="{{ $tariff->scope === 'villa' ? 'Zorunlu' : 'Yok' }}"
                                                           class="field-input !py-1.5 max-w-[9rem]">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach

    {{-- Yeni tarife --}}
    <div x-data="{ open: false }" class="surface overflow-hidden">
        <button type="button" @click="open = !open" class="flex w-full items-center justify-between px-6 py-4 text-left">
            <span class="font-display text-lg font-semibold text-ink">Yeni tarife ekle</span>
            <svg class="h-5 w-5 text-ink-subtle transition-transform" :class="open ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
        </button>

        <form x-show="open" x-cloak method="POST" action="{{ route('admin.tariffs.store') }}" class="grid gap-4 border-t border-line p-6 sm:grid-cols-2">
            @csrf
            <div>
                <label class="field-label">Tesis</label>
                <select name="facility_id" required class="field-input">
                    @foreach ($facilities as $facility)
                        <option value="{{ $facility->id }}">{{ $facility->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="field-label">Yıl</label>
                <input type="number" name="year" value="{{ $year }}" min="2020" max="2100" required class="field-input">
            </div>
            <div class="sm:col-span-2">
                <label class="field-label">Tarife adı</label>
                <input type="text" name="name" required placeholder="Örn. Çolaklı · 1 ve 3. Devreler (İndirimli)" class="field-input">
            </div>
            <div>
                <label class="field-label">Kapsam</label>
                <select name="scope" required class="field-input">
                    <option value="room">Tablo 1 · Oda</option>
                    <option value="villa">Tablo 2 · Villa</option>
                </select>
            </div>
            <div>
                <label class="field-label">Boş yatak ücreti</label>
                <input type="number" step="0.01" min="0" name="empty_bed_fee" placeholder="Alınmaz" class="field-input">
            </div>
            <label class="flex cursor-pointer items-center gap-2 text-sm sm:col-span-2">
                <input type="checkbox" name="is_discounted" value="1" class="rounded border-line text-accent-600 dark:text-accent-400 focus:ring-accent-500">
                İndirimli devre tarifesi
            </label>
            <div class="sm:col-span-2">
                <button type="submit" class="btn-primary">Tarife Ekle</button>
            </div>
        </form>
    </div>
</x-layouts.admin>
