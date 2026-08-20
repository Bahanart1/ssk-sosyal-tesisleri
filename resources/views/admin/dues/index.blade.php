<x-layouts.admin title="Aidatlar">

    <div x-data="{ editing: {}, accrueOpen: false }">

        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="section-label">Yönetim</p>
                <h1 class="page-title mt-1">Üyelik aidatları</h1>
                <p class="page-subtitle">
                    İçinde bulunulan yıl dahil önceki yıllara ait aidat borcu bulunan üyelerin
                    müracaat formları işleme alınmaz.
                </p>
            </div>

            @if ($summary['missing'] > 0)
                <button @click="accrueOpen = true" class="btn-primary shrink-0">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    {{ $year }} tahakkuku oluştur ({{ $summary['missing'] }} üye)
                </button>
            @endif
        </div>

        {{-- Yılın özeti --}}
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-charts.stat label="{{ $year }} tahakkuku"
                           value="₺{{ number_format($summary['accrued'], 0, ',', '.') }}"
                           :hint="$summary['total_count'] . ' üyeye açıldı'" />

            <x-charts.stat label="Tahsil edilen"
                           value="₺{{ number_format($summary['collected'], 0, ',', '.') }}"
                           :hint="$summary['paid_count'] . ' üye ödedi'" />

            <x-charts.stat label="Kalan borç"
                           value="₺{{ number_format($summary['outstanding'], 0, ',', '.') }}"
                           :hint="$summary['unpaid_count'] . ' üye borçlu'" />

            <x-charts.stat label="Tahsilat oranı"
                           value="%{{ number_format($summary['rate'] * 100, 0, ',', '.') }}"
                           :hint="$summary['waived_count'] > 0 ? $summary['waived_count'] . ' üye muaf tutuldu' : null" />
        </div>

        {{-- Tahsilat oranı çubuğu --}}
        @if ($summary['total_count'] > 0)
            <x-panel class="mb-6" title="{{ $year }} yılı tahsilat durumu"
                     subtitle="Tahakkuk açılan {{ $summary['total_count'] }} üyenin dağılımı">
                <x-charts.meter :rows="[
                    ['label' => 'Ödendi', 'value' => $summary['paid_count'], 'share' => $summary['total_count'] ? $summary['paid_count'] / $summary['total_count'] : 0, 'tone' => 'green', 'href' => request()->fullUrlWithQuery(['status' => 'paid'])],
                    ['label' => 'Borçlu', 'value' => $summary['unpaid_count'], 'share' => $summary['total_count'] ? $summary['unpaid_count'] / $summary['total_count'] : 0, 'tone' => 'red', 'href' => request()->fullUrlWithQuery(['status' => 'unpaid'])],
                    ['label' => 'Muaf', 'value' => $summary['waived_count'], 'share' => $summary['total_count'] ? $summary['waived_count'] / $summary['total_count'] : 0, 'tone' => 'gray', 'href' => request()->fullUrlWithQuery(['status' => 'waived'])],
                ]" />
            </x-panel>
        @endif

        {{-- Süzgeçler --}}
        <form method="GET" class="surface mb-6 flex flex-wrap items-end gap-3 p-4">
            <div>
                <label class="field-label">Yıl</label>
                <select name="year" class="field-input" onchange="this.form.submit()">
                    @foreach ($years as $option)
                        <option value="{{ $option }}" @selected($year === (int) $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[14rem] flex-1">
                <label class="field-label">Ara</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Ad, TC no veya üyelik no" class="field-input">
            </div>
            <div>
                <label class="field-label">Durum</label>
                <select name="status" class="field-input">
                    <option value="">Tümü</option>
                    <option value="paid" @selected(request('status') === 'paid')>Ödendi</option>
                    <option value="unpaid" @selected(request('status') === 'unpaid')>Borçlu</option>
                    <option value="waived" @selected(request('status') === 'waived')>Muaf</option>
                    <option value="missing" @selected(request('status') === 'missing')>Tahakkuk açılmamış</option>
                </select>
            </div>
            <div>
                <label class="field-label">Grup</label>
                <select name="group" class="field-input">
                    <option value="">Tümü</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}" @selected(request('group') == $group->id)>{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-primary">Filtrele</button>
            @if (request()->hasAny(['q', 'status', 'group']))
                <a href="{{ route('admin.dues.index', ['year' => $year]) }}" class="btn-ghost">Temizle</a>
            @endif
        </form>

        {{-- Üye listesi --}}
        <div class="surface overflow-hidden">
            @if ($members->isEmpty())
                <p class="px-6 py-16 text-center text-sm text-ink-subtle">Bu filtreye uyan üye bulunamadı.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Üye</th>
                                <th>Grup</th>
                                <th>{{ $year }} durumu</th>
                                <th>Tutar</th>
                                <th>Ödeme</th>
                                <th>Toplam borç</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($members as $member)
                                @php $due = $member->dues->first(); @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.customers.show', $member) }}" class="font-medium text-ink hover:text-accent-700 dark:hover:text-accent-300">
                                            {{ $member->name }}
                                        </a>
                                        <p class="text-xs text-ink-muted">{{ $member->membership_no ?? $member->maskedTcNo() }}</p>
                                    </td>
                                    <td class="text-xs">{{ $member->customerGroup?->name }}</td>
                                    <td>
                                        @if ($due)
                                            <span class="badge-{{ $due->statusTone() }}">{{ $due->statusLabel() }}</span>
                                        @else
                                            <span class="badge-amber">Tahakkuk yok</span>
                                        @endif
                                    </td>
                                    <td class="tabular-nums">
                                        @if ($due)<x-money :value="$due->amount" />@else—@endif
                                    </td>
                                    <td class="text-xs text-ink-muted">
                                        @if ($due?->status === 'paid')
                                            {{ $due->paid_at?->format('d.m.Y') }}<br>{{ $due->methodLabel() }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        @if (($member->outstanding_total ?? 0) > 0)
                                            <x-money :value="$member->outstanding_total" class="font-semibold text-red-600 tabular-nums" />
                                        @else
                                            <span class="text-xs text-emerald-700">Borç yok</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-1">
                                            @if ($due && $due->status === 'unpaid')
                                                <form method="POST" action="{{ route('admin.dues.paid', $due) }}">
                                                    @csrf
                                                    <input type="hidden" name="method" value="bank_transfer">
                                                    <button class="btn-accent !px-2.5 !py-1 text-xs">Tahsil edildi</button>
                                                </form>
                                            @elseif (@$due->status === 'review')
                                                    <a href="{{ route('documents.dues-receipt', $due) }}" target="_blank" rel="noopener"
                                                       class="btn-secondary !px-2.5 !py-1 text-xs">Dekont</a>
                                                    <form method="POST" action="{{ route('admin.dues.paid', $due) }}">
                                                        @csrf
                                                        <input type="hidden" name="method" value="bank_transfer">
                                                        <button class="btn-accent !px-2.5 !py-1 text-xs">Onayla</button>
                                                    </form>
                                                @endif
                                            @if ($due)
                                                <button type="button" class="btn-ghost !px-2.5 !py-1 text-xs"
                                                        @click="editing = {{ Illuminate\Support\Js::from([
                                                            'id' => $due->id,
                                                            'member' => $member->name,
                                                            'year' => $due->year,
                                                            'amount' => (float) $due->amount,
                                                            'status' => $due->status,
                                                            'paid_at' => $due->paid_at?->toDateString(),
                                                            'method' => $due->method,
                                                            'receipt_no' => $due->receipt_no,
                                                            'note' => $due->note,
                                                        ]) }}">Düzenle</button>
                                            @else
                                                <form method="POST" action="{{ route('admin.dues.store', $member) }}">
                                                    @csrf
                                                    <input type="hidden" name="year" value="{{ $year }}">
                                                    <input type="hidden" name="amount" value="{{ $defaultAmount }}">
                                                    <button class="btn-secondary !px-2.5 !py-1 text-xs">Tahakkuk aç</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="mt-6">{{ $members->links() }}</div>

        {{-- Toplu tahakkuk --}}
        <template x-teleport="body">
            <div x-show="accrueOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="modal-scrim" @click="accrueOpen = false"></div>
                <div class="modal-panel" x-transition>
                    <h3 class="font-display text-lg font-semibold text-ink">{{ $year }} yılı tahakkuku</h3>
                    <p class="mt-1 text-sm text-ink-muted">
                        Bu yıl için kaydı bulunmayan <strong>{{ $summary['missing'] }}</strong> aktif üyeye borç kaydı açılır.
                        Mevcut kayıtlar değiştirilmez.
                    </p>
                    <form method="POST" action="{{ route('admin.dues.accrue') }}" class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="year" value="{{ $year }}">
                        <div>
                            <label class="field-label">Kişi başı aidat tutarı (₺)</label>
                            <input type="number" step="0.01" min="0" name="amount" value="{{ $defaultAmount }}" required class="field-input">
                            @error('amount') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex gap-3">
                            <button type="button" @click="accrueOpen = false" class="btn-secondary flex-1">Vazgeç</button>
                            <button type="submit" class="btn-primary flex-1">Tahakkuku Oluştur</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        {{-- Kayıt düzenleme --}}
        <template x-teleport="body">
            <div x-show="editing.id" x-cloak class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 py-8">
                <div class="modal-scrim" @click="editing = {}"></div>
                <div class="modal-panel" x-transition>
                    <h3 class="font-display text-lg font-semibold text-ink">Aidat kaydı</h3>
                    <p class="mt-1 text-sm text-ink-muted">
                        <span x-text="editing?.member"></span> · <span x-text="editing?.year"></span>
                    </p>

                    <form method="POST" :action="'{{ url('admin/aidatlar') }}/' + editing?.id" class="mt-4 space-y-4">
                        @csrf
                        @method('PUT')

                        <div>
                            <label class="field-label">Tutar (₺)</label>
                            <input type="number" step="0.01" min="0" name="amount" x-model.number="editing.amount" required class="field-input">
                        </div>

                        <div>
                            <label class="field-label">Durum</label>
                            <select name="status" x-model="editing.status" class="field-input">
                                <option value="unpaid">Borçlu</option>
                                <option value="paid">Ödendi</option>
                                <option value="waived">Muaf</option>
                            </select>
                        </div>

                        <div x-show="editing.status === 'paid'" x-cloak class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="field-label">Ödeme tarihi</label>
                                <input type="date" name="paid_at" x-model="editing.paid_at" class="field-input">
                            </div>
                            <div>
                                <label class="field-label">Yöntem</label>
                                <select name="method" x-model="editing.method" class="field-input">
                                    <option value="">Seçin</option>
                                    @foreach ($methods as $value => $labelText)
                                        <option value="{{ $value }}">{{ $labelText }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="field-label">Makbuz no <span class="font-normal text-ink-subtle">(opsiyonel)</span></label>
                                <input type="text" name="receipt_no" x-model="editing.receipt_no" class="field-input">
                            </div>
                        </div>

                        <div>
                            <label class="field-label">Not <span class="font-normal text-ink-subtle">(opsiyonel)</span></label>
                            <input type="text" name="note" x-model="editing.note" maxlength="255" class="field-input">
                        </div>

                        <div class="flex gap-3 pt-1">
                            <button type="button" @click="editing = {}" class="btn-secondary flex-1">Vazgeç</button>
                            <button type="submit" class="btn-primary flex-1">Kaydet</button>
                        </div>
                    </form>

                    <form method="POST" :action="'{{ url('admin/aidatlar') }}/' + editing?.id" class="mt-3"
                          @submit="if (! confirm('Bu aidat kaydı silinecek. Onaylıyor musunuz?')) $event.preventDefault()">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full text-xs font-semibold text-red-600 hover:text-red-700">Kaydı sil</button>
                    </form>
                </div>
            </div>
        </template>
    </div>
</x-layouts.admin>
