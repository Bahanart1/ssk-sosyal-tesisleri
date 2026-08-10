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
                <tbody class="divide-y divide-slate-100">
                    @foreach ($weeks as $week)
                        <tr>
                            <td>
                                <p class="font-semibold text-navy-900">{{ $week['label'] }}</p>
                                <p class="text-xs text-slate-400">Hafta {{ $week['week_no'] }} · {{ $week['year'] }}</p>
                            </td>
                            <td class="text-sm text-slate-600">{{ $week['range'] }}</td>
                            <td>
                                @if ($week['is_open'])
                                    <span class="badge-green">Açık</span>
                                @else
                                    <span class="badge-red">Kapalı</span>
                                @endif
                            </td>
                            <td class="max-w-[12rem] text-sm text-slate-500">{{ $week['note'] ?: '—' }}</td>
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

        <ul class="divide-y divide-slate-100 lg:hidden">
            @foreach ($weeks as $week)
                <li class="space-y-3 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-navy-900">{{ $week['label'] }}</p>
                            <p class="text-xs text-slate-500">{{ $week['range'] }}</p>
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
