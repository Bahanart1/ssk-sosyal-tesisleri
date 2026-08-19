<x-layouts.admin title="İadeler">

    <div x-data="{ odenen: null }">
        <div class="mb-6">
            <p class="section-label">Yönetim</p>
            <h1 class="page-title mt-1">İadeler</h1>
            <p class="page-subtitle">
                @if ($tur === 'fazla')
                    Kişi değişikliği sonrası oluşan fazla ödemeler. İade taraflar arasında yapılır;
                    yapıldığında "Ödendi" olarak işaretleyin.
                @else
                    Reddedilen başvuruların peşinatı buraya kendiliğinden düşer ve kesintisiz iade edilir;
                    üye iptallerinde kırtasiye ve hizmet bedeli düşülür.
                @endif
            </p>
        </div>

        {{-- Tür sekmeleri --}}
        <div class="mb-5 flex flex-wrap gap-2">
            <a href="{{ route('admin.refunds.index', ['tur' => 'pesinat']) }}"
               class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium transition-all {{ $tur === 'pesinat' ? 'bg-accent-600 text-white' : 'bg-surface text-ink ring-1 ring-line hover:bg-surface-alt' }}">
                Peşinatlar
                @if ($turCounts['pesinat'] > 0)
                    <span class="rounded-md px-1.5 py-0.5 text-[10px] {{ $tur === 'pesinat' ? 'bg-white/15' : 'bg-surface-sunken' }}">{{ $turCounts['pesinat'] }}</span>
                @endif
            </a>
            <a href="{{ route('admin.refunds.index', ['tur' => 'fazla']) }}"
               class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium transition-all {{ $tur === 'fazla' ? 'bg-accent-600 text-white' : 'bg-surface text-ink ring-1 ring-line hover:bg-surface-alt' }}">
                Fazla ödemeler
                @if ($turCounts['fazla'] > 0)
                    <span class="rounded-md px-1.5 py-0.5 text-[10px] {{ $tur === 'fazla' ? 'bg-white/15' : 'bg-surface-sunken' }}">{{ $turCounts['fazla'] }}</span>
                @endif
            </a>
        </div>

        {{-- Durum sekmeleri --}}
        <div class="surface mb-5 overflow-hidden">
            <div class="grid grid-cols-3 gap-px bg-line">
                @foreach ($statuses as $key => $label)
                    @php
                        $aktif = $status === $key;
                        $satir = $counts[$key] ?? null;
                    @endphp
                    <a href="{{ route('admin.refunds.index', ['status' => $key, 'tur' => $tur]) }}"
                       class="flex flex-col gap-0.5 px-4 py-3 transition-colors {{ $aktif ? 'bg-accent-50 dark:bg-accent-900/25' : 'bg-surface hover:bg-surface-alt' }}">
                        <span class="text-lg font-semibold tabular-nums text-ink">{{ (int) ($satir->adet ?? 0) }}</span>
                        <span class="text-[11px] font-medium {{ $aktif ? 'text-accent-700 dark:text-accent-300' : 'text-ink-muted' }}">{{ $label }}</span>
                        <span class="text-[11px] tabular-nums text-ink-subtle"><x-money :value="(float) ($satir->tutar ?? 0)" /></span>
                    </a>
                @endforeach
            </div>

            <p class="border-t border-line bg-surface-alt px-4 py-2.5 text-xs text-ink-muted">
                @switch($status)
                    @case('pending') Üye hesabını bildirdi. Havaleyi yapıp aşağıdan "Ödendi" olarak işaretleyin. @break
                    @case('awaiting_iban') Üyenin IBAN bildirmesi bekleniyor. Bildirim yapılınca kayıt "Ödeme bekliyor"a geçer. @break
                    @case('paid') Ödemesi tamamlanmış iadeler. @break
                @endswitch
            </p>
        </div>

        <form method="GET" class="surface mb-6 p-4">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="hidden" name="tur" value="{{ $tur }}">
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[16rem] flex-1">
                    <label for="f-q" class="field-label">Ara</label>
                    <input id="f-q" type="text" name="q" value="{{ request('q') }}"
                           placeholder="Başvuru no, ad, TC veya üyelik no" class="field-input">
                </div>
                <button type="submit" class="btn-primary">Filtrele</button>
                @if (request('q'))
                    <a href="{{ route('admin.refunds.index', ['status' => $status]) }}" class="btn-ghost">Temizle</a>
                @endif
            </div>
        </form>

        <div class="surface overflow-hidden">
            @if ($refunds->isEmpty())
                <p class="px-6 py-16 text-center text-sm text-ink-subtle">Bu listede iade kaydı yok.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Başvuru</th>
                                <th>Üye</th>
                                <th>Gerekçe</th>
                                <th class="text-right">Tahsil</th>
                                <th class="text-right">Kesinti</th>
                                <th class="text-right">İade</th>
                                <th>Hesap</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($refunds as $refund)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.reservations.show', $refund->reservation) }}"
                                           class="font-mono text-xs hover:text-accent-600 dark:hover:text-accent-400">{{ $refund->reservation->code }}</a>
                                        <p class="text-[11px] text-ink-muted">{{ $refund->reservation->facility->name }} · {{ $refund->reservation->period->label() }}</p>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.customers.show', $refund->user) }}" class="font-medium hover:text-accent-600 dark:hover:text-accent-400">
                                            {{ $refund->user->name }}
                                        </a>
                                        <p class="text-[11px] text-ink-muted">{{ $refund->user->membership_no ?? $refund->user->maskedTcNo() }}</p>
                                    </td>
                                    <td class="text-xs">{{ $refund->reasonLabel() }}</td>
                                    <td class="text-right tabular-nums text-ink-muted"><x-money :value="$refund->gross_amount" /></td>
                                    <td class="text-right tabular-nums">
                                        @if ((float) $refund->deduction > 0)
                                            <x-money :value="$refund->deduction" />
                                        @else
                                            <span class="text-ink-subtle">—</span>
                                        @endif
                                    </td>
                                    <td class="text-right"><x-money :value="$refund->amount" class="font-semibold" /></td>
                                    <td class="text-xs">
                                        @if ($refund->iban)
                                            <p class="font-mono text-[11px]">{{ $refund->ibanFormatted() }}</p>
                                            <p class="text-ink-muted">{{ $refund->account_holder }}</p>
                                        @else
                                            <span style="color: var(--status-warn)">IBAN bekleniyor</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if ($refund->isPaid())
                                            <p class="text-[11px] text-ink-muted">{{ $refund->paid_at->format('d.m.Y') }}</p>
                                            <p class="text-[11px] text-ink-subtle">{{ $refund->reference_no ?? $refund->processor?->name }}</p>
                                        @elseif ($refund->iban)
                                            <button type="button" class="btn-primary !px-3 !py-1.5 text-xs"
                                                    @click="odenen = {{ Illuminate\Support\Js::from([
                                                        'id' => $refund->id,
                                                        'name' => $refund->user->name,
                                                        'iban' => $refund->ibanFormatted(),
                                                        'holder' => $refund->account_holder,
                                                        'amount' => number_format((float) $refund->amount, 2, ',', '.') . ' ₺',
                                                    ]) }}">Ödendi işaretle</button>
                                        @else
                                            <span class="text-[11px] text-ink-subtle">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="mt-6">{{ $refunds->links() }}</div>

        {{-- Ödeme onayı --}}
        <template x-teleport="body">
            <div x-show="odenen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4 py-8"
                 @keydown.escape.window="odenen = null">
                <div class="modal-scrim" @click="odenen = null"></div>
                <div class="modal-panel" x-transition>
                    <h3 class="font-display text-lg font-semibold text-ink">İadeyi ödendi olarak işaretle</h3>
                    <p class="mt-1 text-sm text-ink-muted">
                        <span x-text="odenen?.name"></span> adına
                        <strong class="text-ink" x-text="odenen?.amount"></strong> havale edildiğini onaylıyorsunuz.
                    </p>

                    <div class="mt-3 rounded-lg bg-surface-sunken px-3 py-2.5">
                        <p class="font-mono text-xs text-ink" x-text="odenen?.iban"></p>
                        <p class="text-[11px] text-ink-muted" x-text="odenen?.holder"></p>
                    </div>

                    <form method="POST" :action="'{{ url('admin/iadeler') }}/' + odenen?.id + '/ode'" class="mt-4 space-y-4">
                        @csrf
                        <div>
                            <label class="field-label">Havale referansı <span class="font-normal text-ink-subtle">(opsiyonel)</span></label>
                            <input type="text" name="reference_no" maxlength="60" class="field-input" placeholder="Banka işlem numarası">
                        </div>
                        <div>
                            <label class="field-label">Not <span class="font-normal text-ink-subtle">(opsiyonel)</span></label>
                            <input type="text" name="note" maxlength="255" class="field-input">
                        </div>
                        <div class="flex gap-3 pt-1">
                            <button type="button" @click="odenen = null" class="btn-secondary flex-1">Vazgeç</button>
                            <button type="submit" class="btn-primary flex-1">Ödendi işaretle</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>

</x-layouts.admin>
