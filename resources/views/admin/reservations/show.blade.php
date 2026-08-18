<x-layouts.admin :title="'Başvuru ' . $reservation->code">

    <div x-data="{ rejectOpen: false, cancelOpen: false }" class="mx-auto max-w-4xl">
        <a href="{{ route('admin.reservations.index') }}" class="back-link">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Başvurular
        </a>

        <div class="mt-4 mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="section-label">Başvuru</p>
                <h1 class="page-title mt-1">{{ $reservation->code }}</h1>
                <p class="page-subtitle">
                    {{ $reservation->created_at->translatedFormat('d F Y H:i') }} ·
                    Müracaat tarihi {{ $reservation->application_date->translatedFormat('d F Y') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-status-badge :status="$reservation->status" class="!px-3 !py-1.5 !text-sm" />
                @if (! in_array($reservation->status, ['paid', 'cancelled'], true))
                    <a href="{{ route('admin.reservations.edit', $reservation) }}" class="btn-primary">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" /></svg>
                        Düzenle ve Onayla
                    </a>
                @endif
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                {{-- Konaklama --}}
                <div class="surface overflow-hidden">
                    <div class="border-b border-line px-6 py-4">
                        <h2 class="font-display text-lg font-semibold text-ink">Konaklama</h2>
                    </div>
                    <div class="divide-y divide-line">
                        <div class="flex justify-between px-6 py-3 text-sm"><span class="text-ink-muted">Tesis</span><span class="font-medium text-ink">{{ $reservation->facility->name }}</span></div>
                        <div class="flex justify-between px-6 py-3 text-sm"><span class="text-ink-muted">Oda tipi</span><span class="font-medium text-ink">{{ $reservation->roomType->name }} ({{ $reservation->roomType->bed_count }} yatak)</span></div>
                        <div class="px-6 py-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-ink-muted">Atanan oda</span>
                                @if ($reservation->room)
                                    <span class="font-medium text-ink">{{ $reservation->room->label() }}</span>
                                @else
                                    <span class="text-ink-subtle">Atanmadı</span>
                                @endif
                            </div>

                            @if (in_array($reservation->status, ['pending', 'approved', 'paid'], true))
                                @php $bosSayisi = $availableRooms->flatten()->count(); @endphp
                                <form method="POST" action="{{ route('admin.reservations.assign-room', $reservation) }}"
                                      class="mt-3 flex flex-wrap items-center gap-2">
                                    @csrf
                                    <select name="room_id" class="field-input !py-1.5 flex-1 text-xs">
                                        <option value="">Atanmadı</option>
                                        @if ($reservation->room)
                                            <option value="{{ $reservation->room->id }}" selected>{{ $reservation->room->label() }}</option>
                                        @endif
                                        @foreach ($availableRooms as $blok => $odalar)
                                            <optgroup label="{{ $blok }} — {{ $odalar->count() }} boş">
                                                @foreach ($odalar as $oda)
                                                    @continue($reservation->room && $oda->id === $reservation->room->id)
                                                    <option value="{{ $oda->id }}">{{ $oda->label() }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn-primary !px-3 !py-1.5 text-xs">Kaydet</button>
                                </form>
                                @error('room_id') <p class="field-error">{{ $message }}</p> @enderror
                                <div class="mt-1.5 space-y-1 text-[11px] text-ink-subtle">
                                    <p>
                                        @if ($bosSayisi > 0)
                                            {{ $reservation->period->label() }}@if ($reservation->secondPeriod) + {{ $reservation->secondPeriod->label() }}@endif
                                            için {{ $bosSayisi }} {{ $reservation->roomType->name }} seçilebilir.
                                            <a href="{{ route('admin.rooms.index', ['tesis' => $reservation->facility->slug, 'devre' => $reservation->period_id]) }}"
                                               class="hover:underline">Blok görünümü</a>
                                        @else
                                            Bu devrede boşta {{ $reservation->roomType->name }} kalmadı.
                                        @endif
                                    </p>

                                    {{-- Listede neden yalnızca bazı blokların çıktığını açıklar --}}
                                    @if ($roomTypeBlocks)
                                        <p>
                                            Bu oda tipi yalnızca şu bloklarda bulunuyor:
                                            <strong class="text-ink-muted">{{ implode(', ', $roomTypeBlocks) }}</strong>.
                                        </p>
                                    @endif

                                    @if ($alternateTypes->isNotEmpty())
                                        <p>
                                            Aynı yatak sayısında
                                            <strong class="text-ink-muted">{{ $alternateTypes->pluck('name')->join(', ') }}</strong>
                                            de var; ücreti farklı olduğu için bu listede çıkmaz.
                                            Gerekiyorsa önce
                                            <a href="{{ route('admin.reservations.edit', $reservation) }}" class="hover:underline">oda tipini değiştirin</a>.
                                        </p>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div class="flex justify-between gap-4 px-6 py-3 text-sm">
                            <span class="text-ink-muted">Devre</span>
                            <span class="text-right font-medium text-ink">
                                {{ $reservation->period->label() }}@if ($reservation->secondPeriod) + {{ $reservation->secondPeriod->label() }}@endif
                                <span class="block text-xs font-normal text-ink-muted">{{ $reservation->start_date->translatedFormat('d F Y') }} – {{ $reservation->end_date->translatedFormat('d F Y') }} · {{ $reservation->nights }} gün</span>
                            </span>
                        </div>
                        @if ($reservation->ground_floor_request)
                            <div class="flex justify-between gap-4 bg-amber-50/60 px-6 py-3 text-sm">
                                <span class="font-medium text-amber-800">Zemin kat talebi</span>
                                <span class="max-w-sm text-right text-amber-900">
                                    {{ $reservation->ground_floor_note }}
                                    @if ($reservation->health_report_path)
                                        <a href="{{ route('documents.health-report', $reservation) }}" target="_blank" rel="noopener"
                                           class="mt-1 block text-xs font-semibold text-accent-700 dark:text-accent-300 underline">Sağlık raporunu görüntüle</a>
                                    @endif
                                </span>
                            </div>
                        @endif
                        @if ($reservation->note)
                            <div class="flex justify-between gap-4 px-6 py-3 text-sm"><span class="text-ink-muted">Üye notu</span><span class="max-w-sm text-right text-ink">{{ $reservation->note }}</span></div>
                        @endif
                    </div>
                </div>

                {{-- Kişiler --}}
                <div class="surface overflow-hidden">
                    <div class="border-b border-line px-6 py-4">
                        <h2 class="font-display text-lg font-semibold text-ink">Konaklayacak kişiler ({{ $reservation->guests->count() }})</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Ad Soyad</th>
                                    <th>TC No</th>
                                    <th>Doğum</th>
                                    <th>Yakınlık</th>
                                    <th>Grup</th>
                                    <th>Yaş grubu</th>
                                    <th>Tutar</th>
                                    <th>Kimlik</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($reservation->guests as $guest)
                                    <tr>
                                        <td class="font-medium">{{ $guest->full_name }}</td>
                                        <td class="font-mono text-xs">{{ $guest->tc_no }}</td>
                                        <td class="text-xs">{{ $guest->birth_date->format('d.m.Y') }}</td>
                                        <td class="text-xs">{{ $guest->relationLabel() }}</td>
                                        <td class="text-xs">{{ $guest->customerGroup->name }}</td>
                                        <td class="text-xs">
                                            {{ $guest->ageCategoryLabel() }}
                                            @if ($guest->wants_meal)
                                                <span class="badge-amber !py-0.5 !text-[10px]">yemekli</span>
                                            @endif
                                        </td>
                                        <td><x-money :value="$guest->line_total" zero="Ücretsiz" class="text-xs font-semibold" /></td>
                                        <td>
                                            @if ($guest->id_document_path)
                                                <a href="{{ route('documents.identity', $guest) }}" target="_blank" rel="noopener"
                                                   class="btn-ghost !px-2.5 !py-1 text-xs">Görüntüle</a>
                                            @else
                                                <span class="badge-red !py-0.5 !text-[10px]">Eksik</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Ödemeler --}}
                <div class="surface overflow-hidden">
                    <div class="border-b border-line px-6 py-4">
                        <h2 class="font-display text-lg font-semibold text-ink">Ödemeler</h2>
                    </div>
                    @if ($reservation->payments->isEmpty())
                        <p class="px-6 py-8 text-center text-sm text-ink-subtle">Ödeme kaydı yok.</p>
                    @else
                        <ul class="divide-y divide-line">
                            @foreach ($reservation->payments->sortBy('created_at') as $payment)
                                <li class="px-6 py-3.5">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium text-ink">
                                                {{ $payment->kindLabel() }} · {{ $payment->methodLabel() }}
                                                @if ($payment->installment > 1) · {{ $payment->installment }} taksit @endif
                                            </p>
                                            <p class="text-xs text-ink-muted">
                                                {{ $payment->reference_no }} ·
                                                {{ ($payment->paid_at ?? $payment->created_at)->translatedFormat('d F Y H:i') }}
                                                @if ($payment->verifier) · {{ $payment->verifier->name }} doğruladı @endif
                                                @if ($payment->failure_reason) · {{ $payment->failure_reason }} @endif
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <x-money :value="$payment->amount" class="text-sm font-semibold text-ink" />
                                            <x-status-badge :status="$payment->status" />
                                        </div>
                                    </div>

                                    @if ($payment->method === 'bank_transfer' && $payment->status === 'pending')
                                        <div x-data="{ rejectPayment: false }" class="mt-2.5 flex flex-wrap gap-2">
                                            @if ($payment->receipt_path)
                                                <a href="{{ route('documents.receipt', $payment) }}" target="_blank" rel="noopener"
                                                   class="btn-secondary !px-3 !py-1.5 text-xs">Dekontu Görüntüle</a>
                                            @endif
                                            <form method="POST" action="{{ route('admin.payments.verify', $payment) }}">
                                                @csrf
                                                <button class="btn-accent !px-3 !py-1.5 text-xs">Doğrula</button>
                                            </form>
                                            <button type="button" @click="rejectPayment = !rejectPayment" class="btn-ghost !px-3 !py-1.5 text-xs !text-red-600">Reddet</button>

                                            <form x-show="rejectPayment" x-cloak method="POST" action="{{ route('admin.payments.reject', $payment) }}" class="mt-2 flex w-full gap-2">
                                                @csrf
                                                <input type="text" name="reason" required placeholder="Red gerekçesi" class="field-input !py-1.5 text-xs">
                                                <button class="btn-danger !px-3 !py-1.5 text-xs">Onayla</button>
                                            </form>
                                        </div>
                                    @elseif ($payment->receipt_path)
                                        <a href="{{ route('documents.receipt', $payment) }}" target="_blank" rel="noopener"
                                           class="btn-ghost mt-2 !px-2.5 !py-1 text-xs">Dekont</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            {{-- Yan panel --}}
            <div class="space-y-6">
                {{-- Üye --}}
                <div class="surface overflow-hidden">
                    <div class="border-b border-line px-5 py-3.5">
                        <h2 class="font-display text-base font-semibold text-ink">Başvuru sahibi</h2>
                    </div>
                    <div class="divide-y divide-line text-sm">
                        <div class="flex justify-between px-5 py-2.5"><span class="text-ink-muted">Ad soyad</span><span class="font-medium text-ink">{{ $reservation->user->name }}</span></div>
                        <div class="flex justify-between px-5 py-2.5"><span class="text-ink-muted">Üyelik no</span><span class="font-medium text-ink">{{ $reservation->user->membership_no ?? '-' }}</span></div>
                        <div class="flex justify-between px-5 py-2.5"><span class="text-ink-muted">TC no</span><span class="font-mono text-xs text-ink">{{ $reservation->user->tc_no }}</span></div>
                        <div class="flex justify-between px-5 py-2.5"><span class="text-ink-muted">Telefon</span><span class="font-medium text-ink">{{ $reservation->user->phone ?? '-' }}</span></div>
                        <div class="flex justify-between px-5 py-2.5"><span class="text-ink-muted">Grup</span><span class="font-medium text-ink">{{ $reservation->user->customerGroup?->name ?? '-' }}</span></div>
                        <div class="flex justify-between px-5 py-2.5">
                            <span class="text-ink-muted">Aidat</span>
                            @if (! $reservation->user->isMember())
                                <span class="font-medium text-ink-muted">Muaf</span>
                            @elseif ($reservation->user->hasDuesDebt())
                                <a href="{{ route('admin.customers.show', $reservation->user) }}" class="font-medium text-red-600 hover:underline">
                                    Borçlu · <x-money :value="$reservation->user->duesDebtTotal()" />
                                </a>
                            @else
                                <span class="font-medium text-emerald-700">
                                    Güncel @if ($paidThrough = $reservation->user->duesPaidThrough()) ({{ $paidThrough }}) @endif
                                </span>
                            @endif
                        </div>
                        <div class="flex justify-between px-5 py-2.5">
                            <span class="text-ink-muted">Üye kartı</span>
                            <a href="{{ route('admin.customers.show', $reservation->user) }}" class="font-medium text-accent-700 dark:text-accent-300 hover:underline">Aç →</a>
                        </div>
                    </div>
                </div>

                {{-- Tutar --}}
                <div class="surface overflow-hidden">
                    <div class="border-b border-line px-5 py-3.5">
                        <h2 class="font-display text-base font-semibold text-ink">Tutar</h2>
                    </div>
                    <div class="divide-y divide-line text-sm">
                        @if ($reservation->surcharge_per_person_day > 0)
                            <div class="flex justify-between px-5 py-2.5"><span class="text-ink-muted">Müracaat farkı</span><x-money :value="$reservation->surcharge_per_person_day" class="font-medium text-ink" /></div>
                        @endif
                        <div class="flex justify-between px-5 py-2.5"><span class="text-ink-muted">Konaklama</span><x-money :value="$reservation->accommodation_total" class="font-medium text-ink" /></div>
                        @if ($reservation->empty_bed_total > 0)
                            <div class="flex justify-between px-5 py-2.5"><span class="text-ink-muted">Boş yatak ({{ $reservation->empty_bed_count }})</span><x-money :value="$reservation->empty_bed_total" class="font-medium text-ink" /></div>
                        @endif
                        @if ((float) $reservation->adjustment_amount !== 0.0)
                            <div class="flex justify-between px-5 py-2.5"><span class="text-ink-muted">{{ $reservation->adjustment_note ?: 'Düzeltme' }}</span><x-money :value="$reservation->adjustment_amount" class="font-medium text-ink" /></div>
                        @endif
                        <div class="flex justify-between bg-surface-alt px-5 py-3"><span class="font-semibold text-ink">Toplam</span><x-money :value="$reservation->total_price" class="font-display text-lg font-semibold text-accent-700 dark:text-accent-300" /></div>
                        <div class="flex justify-between px-5 py-2.5"><span class="text-ink-muted">Peşinat</span><x-money :value="$reservation->deposit_amount" class="font-medium text-ink" /></div>
                        <div class="flex justify-between px-5 py-2.5"><span class="text-ink-muted">Tahsil edilen</span><x-money :value="$reservation->paidTotal()" class="font-medium text-ink" /></div>
                        <div class="flex justify-between px-5 py-2.5"><span class="font-semibold text-ink-muted">Kalan bakiye</span><x-money :value="$reservation->balanceDue()" class="font-semibold text-ink" /></div>
                        @if ($reservation->balance_due_date)
                            <div class="flex justify-between px-5 py-2.5"><span class="text-ink-muted">Son ödeme</span><span class="font-medium text-ink">{{ $reservation->balance_due_date->translatedFormat('d F Y') }}</span></div>
                        @endif
                    </div>
                </div>

                {{-- Karar --}}
                @if (in_array($reservation->status, ['pending', 'approved'], true))
                    <div class="surface p-5">
                        <p class="section-label">Karar</p>
                        <div class="mt-3 space-y-2">
                            @if ($reservation->status === 'pending')
                                <a href="{{ route('admin.reservations.edit', $reservation) }}" class="btn-accent w-full">Düzenle ve Onayla</a>
                                <form method="POST" action="{{ route('admin.reservations.approve', $reservation) }}">
                                    @csrf
                                    <button class="btn-primary w-full">Olduğu Gibi Onayla</button>
                                </form>
                            @endif
                            <button type="button" @click="rejectOpen = true" class="btn-secondary w-full !text-red-600">Reddet</button>
                            <button type="button" @click="cancelOpen = true" class="btn-ghost w-full !text-ink-muted">İptal Et</button>
                        </div>
                    </div>
                @endif

                @if ($reservation->admin_note)
                    <div class="surface p-5">
                        <p class="section-label">Yönetim notu</p>
                        <p class="mt-2 whitespace-pre-line text-sm text-ink-muted">{{ $reservation->admin_note }}</p>
                        @if ($reservation->approver)
                            <p class="mt-2 text-xs text-ink-subtle">{{ $reservation->approver->name }} · {{ $reservation->decided_at?->translatedFormat('d F Y H:i') }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- Red modalı --}}
        <template x-teleport="body">
            <div x-show="rejectOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="modal-scrim" @click="rejectOpen = false"></div>
                <div class="modal-panel" x-transition>
                    <h3 class="font-display text-lg font-semibold text-ink">Başvuruyu reddet</h3>
                    <p class="mt-1 text-sm text-ink-muted">Üyeye gösterilecek gerekçeyi girin.</p>
                    <form method="POST" action="{{ route('admin.reservations.reject', $reservation) }}" class="mt-4">
                        @csrf
                        <textarea name="admin_note" required rows="3" class="field-input" placeholder="Red gerekçesi…">{{ old('admin_note') }}</textarea>
                        @error('admin_note') <p class="field-error">{{ $message }}</p> @enderror
                        <div class="mt-4 flex gap-3">
                            <button type="button" @click="rejectOpen = false" class="btn-secondary flex-1">Vazgeç</button>
                            <button type="submit" class="btn-danger flex-1">Reddet</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        {{-- İptal modalı --}}
        <template x-teleport="body">
            <div x-show="cancelOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="modal-scrim" @click="cancelOpen = false"></div>
                <div class="modal-panel" x-transition>
                    <h3 class="font-display text-lg font-semibold text-ink">Başvuruyu iptal et</h3>
                    <form method="POST" action="{{ route('admin.reservations.cancel', $reservation) }}" class="mt-4">
                        @csrf
                        <textarea name="admin_note" required rows="3" class="field-input" placeholder="İptal gerekçesi…"></textarea>
                        <div class="mt-4 flex gap-3">
                            <button type="button" @click="cancelOpen = false" class="btn-secondary flex-1">Vazgeç</button>
                            <button type="submit" class="btn-danger flex-1">İptal Et</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>
</x-layouts.admin>
