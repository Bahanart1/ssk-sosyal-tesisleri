<x-layouts.admin title="Devre Ayarları">

    <div class="mb-6">
        <p class="section-label">Tanımlar</p>
        <h1 class="page-title mt-1">Devre ayarları</h1>
        <p class="page-subtitle">
            Hangi devrenin hangisiyle birleşebileceğini ve her devrenin tarifesini buradan belirleyin.
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

    {{-- Tesis seçimi --}}
    <div class="mb-5 flex flex-wrap gap-2">
        @foreach ($facilities as $item)
            <a href="{{ route('admin.periods.settings', ['facility' => $item->id]) }}"
               class="{{ $facility?->is($item) ? 'btn-primary' : 'btn-secondary' }} !px-4 !py-1.5 text-xs">{{ $item->name }}</a>
        @endforeach
    </div>

    @if (! $facility)
        <div class="surface p-10 text-center text-sm text-ink-muted">Tanımlı tesis yok.</div>
    @else
        <form method="POST" action="{{ route('admin.periods.settings.save') }}">
            @csrf
            @method('PUT')

            <div class="surface overflow-hidden">
                <div class="border-b border-line px-5 py-4">
                    <h2 class="text-base font-semibold text-ink">{{ $facility->name }} · {{ $periods->first()?->year }}</h2>
                    <p class="text-xs text-ink-muted">
                        Bir devrenin tarifesini değiştirmek o devrenin fiyatını değiştirir; tarife tanımları
                        <a href="{{ route('admin.tariffs.index') }}" class="underline">Tarifeler</a> sayfasındadır.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Devre</th>
                                <th>Tarih</th>
                                <th>Birleşebileceği devre</th>
                                <th>Oda tarifesi</th>
                                <th>Villa tarifesi</th>
                                <th class="text-center">İndirimli</th>
                                <th class="text-center">Başvuruya açık</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($periods as $period)
                                <tr>
                                    <td class="font-medium text-ink">{{ $period->label() }}</td>
                                    <td class="text-xs text-ink-muted">{{ $period->dateRange() }}</td>

                                    <td>
                                        <select name="periods[{{ $period->id }}][combines_with_id]" class="field-input !w-auto !min-w-[11rem] !py-1 text-xs">
                                            <option value="">Birleşmez</option>
                                            @foreach ($periods as $aday)
                                                @continue($aday->id === $period->id)
                                                <option value="{{ $aday->id }}" @selected($period->combines_with_id === $aday->id)>
                                                    {{ $aday->label() }} · {{ $aday->dateRange() }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td>
                                        <select name="periods[{{ $period->id }}][room_tariff_id]" class="field-input !w-auto !min-w-[12rem] !py-1 text-xs">
                                            @foreach ($roomTariffs as $tariff)
                                                <option value="{{ $tariff->id }}" @selected($period->room_tariff_id === $tariff->id)>{{ $tariff->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td>
                                        <select name="periods[{{ $period->id }}][villa_tariff_id]" class="field-input !w-auto !min-w-[10rem] !py-1 text-xs">
                                            <option value="">Yok</option>
                                            @foreach ($villaTariffs as $tariff)
                                                <option value="{{ $tariff->id }}" @selected($period->villa_tariff_id === $tariff->id)>{{ $tariff->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <td class="text-center">
                                        <input type="hidden" name="periods[{{ $period->id }}][is_discounted]" value="0">
                                        <input type="checkbox" name="periods[{{ $period->id }}][is_discounted]" value="1"
                                               @checked($period->is_discounted)
                                               class="rounded border-line text-accent-600 focus:ring-accent-500 dark:text-accent-400">
                                    </td>

                                    <td class="text-center">
                                        <input type="hidden" name="periods[{{ $period->id }}][is_open]" value="0">
                                        <input type="checkbox" name="periods[{{ $period->id }}][is_open]" value="1"
                                               @checked($period->is_open)
                                               class="rounded border-line text-accent-600 focus:ring-accent-500 dark:text-accent-400">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-4">
                    <p class="text-xs text-ink-muted">
                        Birleşme tek yönlüdür: 15. devreyi 16'ya bağlarsanız üye 15'i seçtiğinde 16'yı ekleyebilir.
                    </p>
                    <button type="submit" class="btn-primary">Ayarları kaydet</button>
                </div>
            </div>
        </form>
    @endif
</x-layouts.admin>
