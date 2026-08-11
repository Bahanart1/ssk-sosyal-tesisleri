<x-layouts.admin title="Kamp Haftaları">

    <div class="mb-8">
        <p class="section-label">Yönetim</p>
        <h1 class="page-title mt-1">Kamp haftaları</h1>
        <p class="page-subtitle">
            Müşterilerin seçebileceği 1 haftalık kamp dönemlerini açın veya kapatın.
            Kapalı haftalar rezervasyon ekranında görünmez.
        </p>
    </div>

    <div class="mb-6 rounded-xl2 border border-teal-100 bg-teal-50/50 px-4 py-3 text-sm text-teal-900">
        Kamp kuralı: <strong>Pazartesi giriş</strong> → <strong>{{ $campNights }} gece sonra çıkış</strong>.
        Kota/doluluk sizin kontrolünüzdedir; haftayı dolduğunda buradan kapatmanız yeterlidir.
    </div>

    @if ($errors->any())
        <div class="alert-soft mb-6 border-red-200 bg-red-50 text-red-700 ring-red-200">
            <svg class="mt-0.5 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            <p class="font-medium">{{ $errors->first() }}</p>
        </div>
    @endif

    <div class="surface overflow-hidden">
        <div class="hidden overflow-x-auto lg:block">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kamp haftası</th>
                        <th>Giriş / Çıkış</th>
                        <th>Durum</th>
                        <th>Not</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($weeks as $week)
                        <tr>
                            <td>
                                <p class="font-semibold text-navy-900">{{ $week['label'] }}</p>
                                <p class="text-xs text-stone-400">Hafta {{ $week['week_no'] }} · {{ $week['year'] }}</p>
                            </td>
                            <td class="text-sm text-stone-600">{{ $week['range'] }}</td>
                            <td>
                                @if ($week['is_open'])
                                    <span class="badge-green">Açık</span>
                                @else
                                    <span class="badge-red">Kapalı</span>
                                @endif
                            </td>
                            <td class="max-w-[12rem] text-sm text-stone-500">{{ $week['note'] ?: '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.camp-weeks.update') }}" class="flex flex-wrap items-center justify-end gap-2">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="week_start" value="{{ $week['check_in'] }}">
                                    <input type="hidden" name="is_open" value="{{ $week['is_open'] ? 0 : 1 }}">
                                    <input type="text" name="note" value="{{ $week['note'] }}" placeholder="Not (opsiyonel)" class="field-input !py-1.5 sm:max-w-[10rem]">
                                    @if ($week['is_open'])
                                        <button type="submit" class="btn-danger !px-3 !py-1.5 text-xs">Haftayı Kapat</button>
                                    @else
                                        <button type="submit" class="btn-accent !px-3 !py-1.5 text-xs">Haftayı Aç</button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <ul class="divide-y divide-stone-100 lg:hidden">
            @foreach ($weeks as $week)
                <li class="space-y-3 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-navy-900">{{ $week['label'] }}</p>
                            <p class="text-xs text-stone-500">{{ $week['range'] }}</p>
                        </div>
                        @if ($week['is_open'])
                            <span class="badge-green">Açık</span>
                        @else
                            <span class="badge-red">Kapalı</span>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('admin.camp-weeks.update') }}" class="space-y-2">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="week_start" value="{{ $week['check_in'] }}">
                        <input type="hidden" name="is_open" value="{{ $week['is_open'] ? 0 : 1 }}">
                        <input type="text" name="note" value="{{ $week['note'] }}" placeholder="Not (opsiyonel)" class="field-input">
                        @if ($week['is_open'])
                            <button type="submit" class="btn-danger w-full">Haftayı Kapat</button>
                        @else
                            <button type="submit" class="btn-accent w-full">Haftayı Aç</button>
                        @endif
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
</x-layouts.admin>
