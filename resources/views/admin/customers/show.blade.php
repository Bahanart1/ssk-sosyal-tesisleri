<x-layouts.admin :title="$customer->name">

    @php $hasDebt = $customer->hasDuesDebt(); @endphp

    <div x-data="{ editOpen: {{ $errors->edit->any() ? 'true' : 'false' }}, duesOpen: false, editingDue: null }"
         class="mx-auto max-w-5xl">

        <a href="{{ route('admin.customers.index') }}" class="back-link">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Üyeler
        </a>

        {{-- Başlık --}}
        <div class="mt-4 mb-6 flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-accent-100 dark:bg-accent-900/40 font-display text-xl font-semibold text-accent-700 dark:text-accent-200 ring-1 ring-accent-200 dark:ring-accent-700">
                    {{ mb_substr($customer->name, 0, 1) }}
                </div>
                <div>
                    <p class="section-label">{{ $customer->customerGroup?->name ?? 'Grup atanmadı' }}</p>
                    <h1 class="page-title mt-1">{{ $customer->name }}</h1>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        @if ($customer->membership_no)
                            <span class="badge-gray">Üyelik no {{ $customer->membership_no }}</span>
                        @endif
                        <span class="badge-{{ $customer->is_active ? 'green' : 'gray' }}">{{ $customer->is_active ? 'Aktif' : 'Pasif' }}</span>
                        @if ($customer->isMember())
                            <span class="badge-{{ $hasDebt ? 'red' : 'green' }}">
                                {{ $hasDebt ? 'Aidat borçlu' : 'Aidat güncel' }}
                            </span>
                        @else
                            <span class="badge-gray">Aidattan muaf</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('admin.reservations.create', ['uye' => $customer->id]) }}" class="btn-accent">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Adına Rezervasyon
                </a>
                <button @click="editOpen = true" class="btn-primary">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" /></svg>
                    Bilgileri Düzenle
                </button>
            </div>
        </div>

        {{-- Aidat borcu uyarısı --}}
        @if ($hasDebt)
            <div class="alert-soft mb-6 border-red-200 bg-red-50 text-red-800 ring-red-200">
                <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                <div>
                    <p class="font-semibold">
                        Aidat borcu: <x-money :value="$stats['duesDebt']" />
                    </p>
                    <p class="mt-0.5 text-sm">
                        Borç ödenmediği sürece bu üyenin müracaat formları işleme alınmaz. Borçlu yıllar:
                        {{ $customer->outstandingDues()->pluck('year')->implode(', ') }}.
                    </p>
                </div>
            </div>
        @endif

        {{-- Özet --}}
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-charts.stat label="Başvuru" :value="$stats['reservations']" />
            <x-charts.stat label="Konaklanan gün" :value="$stats['nights']" hint="Tahsis edilen devrelerde" />
            <x-charts.stat label="Toplam ödeme" value="₺{{ number_format($stats['collected'], 0, ',', '.') }}" />
            <x-charts.stat label="Bekleyen bakiye" value="₺{{ number_format($stats['outstanding'], 0, ',', '.') }}" />
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Künye --}}
            <div class="surface overflow-hidden">
                <div class="border-b border-line px-5 py-3.5">
                    <h2 class="font-display text-base font-semibold text-ink">Üye bilgileri</h2>
                </div>
                <div class="divide-y divide-line text-sm">
                    <div class="flex justify-between gap-3 px-5 py-2.5"><span class="text-ink-muted">TC kimlik no</span><span class="font-mono text-xs text-ink">{{ $customer->tc_no }}</span></div>
                    <div class="flex justify-between gap-3 px-5 py-2.5"><span class="text-ink-muted">Üyelik no</span><span class="font-medium text-ink">{{ $customer->membership_no ?? '-' }}</span></div>
                    <div class="flex justify-between gap-3 px-5 py-2.5"><span class="text-ink-muted">Telefon</span><span class="font-medium text-ink">{{ $customer->phone ?? '-' }}</span></div>
                    <div class="flex justify-between gap-3 px-5 py-2.5"><span class="shrink-0 text-ink-muted">E-posta</span><span class="break-all text-right font-medium text-ink">{{ $customer->email ?? '-' }}</span></div>
                    <div class="flex justify-between gap-3 px-5 py-2.5"><span class="text-ink-muted">Üyelik tarihi</span><span class="font-medium text-ink">{{ $customer->joined_at?->translatedFormat('d F Y') ?? '-' }}</span></div>
                    <div class="flex justify-between gap-3 px-5 py-2.5"><span class="text-ink-muted">Grup</span><span class="text-right font-medium text-ink">{{ $customer->customerGroup?->name ?? '-' }}</span></div>
                    @if ($customer->address)
                        <div class="px-5 py-2.5">
                            <span class="text-ink-muted">Adres</span>
                            <p class="mt-1 text-ink">{{ $customer->address }}</p>
                        </div>
                    @endif
                    <div class="flex justify-between gap-3 px-5 py-2.5"><span class="text-ink-muted">Kayıt</span><span class="font-medium text-ink">{{ $customer->created_at->translatedFormat('d F Y') }}</span></div>
                </div>
            </div>

            {{-- Aidat geçmişi --}}
            <div class="surface overflow-hidden lg:col-span-2">
                <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
                    <div>
                        <h2 class="font-display text-base font-semibold text-ink">Aidat geçmişi</h2>
                        <p class="text-xs text-ink-muted">
                            @if ($customer->isMember())
                                Aidatı ödenen son yıl: {{ $customer->duesPaidThrough() ?? '—' }}
                            @else
                                Dernek üyesi olmayanlar aidattan muaftır.
                            @endif
                        </p>
                    </div>
                    @if ($customer->isMember())
                        <button @click="duesOpen = true" class="btn-secondary !px-3 !py-1.5 text-xs">Yıl ekle</button>
                    @endif
                </div>

                @if ($customer->dues->isEmpty())
                    <div class="empty-state !py-10">
                        <p class="text-sm text-ink-subtle">Aidat kaydı bulunmuyor.</p>
                        @if ($customer->isMember())
                            <p class="text-xs text-ink-subtle">Tahakkuk açmak için "Yıl ekle" düğmesini kullanın.</p>
                        @endif
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Yıl</th>
                                    <th>Tutar</th>
                                    <th>Durum</th>
                                    <th>Ödeme</th>
                                    <th>Kaydeden</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($customer->dues as $due)
                                    <tr>
                                        <td class="font-medium tabular-nums">{{ $due->year }}</td>
                                        <td class="tabular-nums"><x-money :value="$due->amount" /></td>
                                        <td><span class="badge-{{ $due->statusTone() }}">{{ $due->statusLabel() }}</span></td>
                                        <td class="text-xs text-ink-muted">
                                            @if ($due->status === 'paid')
                                                {{ $due->paid_at?->format('d.m.Y') }} · {{ $due->methodLabel() }}
                                                @if ($due->receipt_no)<br><span class="text-ink-subtle">Makbuz {{ $due->receipt_no }}</span>@endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-xs text-ink-muted">{{ $due->recorder?->name ?? '—' }}</td>
                                        <td class="text-right">
                                            <div class="flex justify-end gap-1">
                                                @if ($due->status === 'unpaid')
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
                                                <button type="button" class="btn-ghost !px-2.5 !py-1 text-xs"
                                                        @click="editingDue = {{ Illuminate\Support\Js::from([
                                                            'id' => $due->id,
                                                            'year' => $due->year,
                                                            'amount' => (float) $due->amount,
                                                            'status' => $due->status,
                                                            'paid_at' => $due->paid_at?->toDateString(),
                                                            'method' => $due->method,
                                                            'receipt_no' => $due->receipt_no,
                                                            'note' => $due->note,
                                                        ]) }}">Düzenle</button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Başvurular --}}
        <div class="surface mt-6 overflow-hidden">
            <div class="border-b border-line px-5 py-3.5">
                <h2 class="font-display text-base font-semibold text-ink">Başvurular ({{ $reservations->count() }})</h2>
            </div>

            @if ($reservations->isEmpty())
                <div class="empty-state !py-10"><p class="text-sm text-ink-subtle">Bu üyenin başvurusu yok.</p></div>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($reservations as $reservation)
                        <li>
                            <a href="{{ route('admin.reservations.show', $reservation) }}"
                               class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 transition-colors hover:bg-accent-50/60 dark:hover:bg-accent-900/20">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-ink">
                                        {{ $reservation->facility->name }} · {{ $reservation->roomType->name }}
                                    </p>
                                    <p class="text-xs text-ink-muted">
                                        {{ $reservation->code }} · {{ $reservation->period->label() }} ·
                                        {{ $reservation->start_date->translatedFormat('d M') }} – {{ $reservation->end_date->translatedFormat('d M Y') }}
                                        ({{ $reservation->nights }} gün)
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <x-money :value="$reservation->total_price" class="text-sm font-semibold tabular-nums text-ink" />
                                    <x-status-badge :status="$reservation->status" :label="$reservation->collectsOnSite() ? 'Tesiste Ödeyecek' : null" />
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Ödemeler --}}
        <div class="surface mt-6 overflow-hidden">
            <div class="border-b border-line px-5 py-3.5">
                <h2 class="font-display text-base font-semibold text-ink">Konaklama ödemeleri ({{ $payments->count() }})</h2>
                <p class="text-xs text-ink-muted">Aidat tahsilatları ayrı olarak yukarıda listelenir.</p>
            </div>

            @if ($payments->isEmpty())
                <div class="empty-state !py-10"><p class="text-sm text-ink-subtle">Ödeme kaydı yok.</p></div>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Başvuru</th>
                                <th>Tür</th>
                                <th>Yöntem</th>
                                <th>Tutar</th>
                                <th>Durum</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($payments as $payment)
                                <tr>
                                    <td class="text-xs">{{ ($payment->paid_at ?? $payment->created_at)->format('d.m.Y') }}</td>
                                    <td class="font-mono text-xs">{{ $payment->reservation->code }}</td>
                                    <td class="text-xs">{{ $payment->kindLabel() }}</td>
                                    <td class="text-xs">
                                        {{ $payment->methodLabel() }}
                                        @if ($payment->installment > 1)<span class="text-ink-subtle"> · {{ $payment->installment }} taksit</span>@endif
                                    </td>
                                    <td class="tabular-nums"><x-money :value="$payment->amount" class="font-semibold" /></td>
                                    <td><x-status-badge :status="$payment->status" :label="$payment->statusLabel()" /></td>
                                    <td class="text-right">
                                        @if ($payment->receipt_path)
                                            <a href="{{ route('documents.receipt', $payment) }}" target="_blank" rel="noopener"
                                               class="btn-ghost !px-2.5 !py-1 text-xs">Dekont</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Bilgi düzenleme --}}
        <template x-teleport="body">
            <div x-show="editOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 py-8">
                <div class="modal-scrim" @click="editOpen = false"></div>
                <div class="modal-panel" x-transition>
                    <h3 class="mb-4 font-display text-lg font-semibold text-ink">Üye bilgilerini düzenle</h3>
                    <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div>
                            <label class="field-label">Ad Soyad</label>
                            <input type="text" name="name" value="{{ old('name', $customer->name) }}" required class="field-input @error('name', 'edit') !border-red-400 @enderror">
                            @error('name', 'edit') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="field-label">TC Kimlik No</label>
                                <input type="text" name="tc_no" maxlength="11" value="{{ old('tc_no', $customer->tc_no) }}" required class="field-input font-mono @error('tc_no', 'edit') !border-red-400 @enderror">
                                @error('tc_no', 'edit') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="field-label">Üyelik No</label>
                                <input type="text" name="membership_no" value="{{ old('membership_no', $customer->membership_no) }}" class="field-input">
                                @error('membership_no', 'edit') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="field-label">Telefon</label>
                                <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}" class="field-input">
                            </div>
                            <div>
                                <label class="field-label">E-posta</label>
                                <input type="email" name="email" value="{{ old('email', $customer->email) }}" class="field-input">
                            </div>
                            <div>
                                <label class="field-label">Üyelik tarihi</label>
                                <input type="date" name="joined_at" value="{{ old('joined_at', $customer->joined_at?->toDateString()) }}" class="field-input">
                            </div>
                            <div>
                                <label class="field-label">Müşteri grubu</label>
                                <select name="customer_group_id" required class="field-input">
                                    @foreach (\App\Models\CustomerGroup::ordered()->get() as $group)
                                        <option value="{{ $group->id }}" @selected(old('customer_group_id', $customer->customer_group_id) == $group->id)>{{ $group->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="field-label">Adres</label>
                            <textarea name="address" rows="2" class="field-input">{{ old('address', $customer->address) }}</textarea>
                        </div>

                        <div>
                            <label class="field-label">Yeni şifre</label>
                            <input type="text" name="password" minlength="6" placeholder="Değiştirmek istemiyorsanız boş bırakın" class="field-input @error('password', 'edit') !border-red-400 @enderror">
                            @error('password', 'edit') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $customer->is_active)) class="rounded border-line text-accent-600 dark:text-accent-400 focus:ring-accent-500">
                            Hesap aktif
                        </label>

                        <div class="flex gap-3 pt-1">
                            <button type="button" @click="editOpen = false" class="btn-secondary flex-1">Vazgeç</button>
                            <button type="submit" class="btn-primary flex-1">Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        {{-- Yeni aidat yılı --}}
        <template x-teleport="body">
            <div x-show="duesOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="modal-scrim" @click="duesOpen = false"></div>
                <div class="modal-panel" x-transition>
                    <h3 class="font-display text-lg font-semibold text-ink">Aidat yılı ekle</h3>
                    <p class="mt-1 text-sm text-ink-muted">{{ $customer->name }} için borç tahakkuku açılır.</p>
                    <form method="POST" action="{{ route('admin.dues.store', $customer) }}" class="mt-4 space-y-4">
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="field-label">Yıl</label>
                                <input type="number" name="year" min="2000" max="2100" value="{{ old('year', $duesYear) }}" required class="field-input">
                                @error('year') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="field-label">Tutar (₺)</label>
                                <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $defaultDuesAmount) }}" required class="field-input">
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" @click="duesOpen = false" class="btn-secondary flex-1">Vazgeç</button>
                            <button type="submit" class="btn-primary flex-1">Ekle</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        {{-- Aidat kaydı düzenleme --}}
        <template x-teleport="body">
            <div x-show="editingDue" x-cloak class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 py-8">
                <div class="modal-scrim" @click="editingDue = null"></div>
                <div class="modal-panel" x-transition>
                    <h3 class="font-display text-lg font-semibold text-ink">
                        <span x-text="editingDue?.year"></span> yılı aidatı
                    </h3>

                    <form method="POST" :action="'{{ url('admin/aidatlar') }}/' + editingDue?.id" class="mt-4 space-y-4">
                        @csrf
                        @method('PUT')

                        <div>
                            <label class="field-label">Tutar (₺)</label>
                            <input type="number" step="0.01" min="0" name="amount" x-model.number="editingDue.amount" required class="field-input">
                        </div>

                        <div>
                            <label class="field-label">Durum</label>
                            <select name="status" x-model="editingDue.status" class="field-input">
                                <option value="unpaid">Borçlu</option>
                                <option value="paid">Ödendi</option>
                                <option value="waived">Muaf</option>
                            </select>
                        </div>

                        <div x-show="editingDue.status === 'paid'" x-cloak class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="field-label">Ödeme tarihi</label>
                                <input type="date" name="paid_at" x-model="editingDue.paid_at" class="field-input">
                            </div>
                            <div>
                                <label class="field-label">Yöntem</label>
                                <select name="method" x-model="editingDue.method" class="field-input">
                                    <option value="">Seçin</option>
                                    @foreach ($methods as $value => $labelText)
                                        <option value="{{ $value }}">{{ $labelText }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="field-label">Makbuz no <span class="font-normal text-ink-subtle">(opsiyonel)</span></label>
                                <input type="text" name="receipt_no" x-model="editingDue.receipt_no" class="field-input">
                            </div>
                        </div>

                        <div>
                            <label class="field-label">Not <span class="font-normal text-ink-subtle">(opsiyonel)</span></label>
                            <input type="text" name="note" x-model="editingDue.note" maxlength="255" class="field-input">
                        </div>

                        <div class="flex gap-3 pt-1">
                            <button type="button" @click="editingDue = null" class="btn-secondary flex-1">Vazgeç</button>
                            <button type="submit" class="btn-primary flex-1">Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>
</x-layouts.admin>
