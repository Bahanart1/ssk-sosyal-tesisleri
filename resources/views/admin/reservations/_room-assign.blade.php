{{--
    Listeden satır içi oda ataması. $options, blok adına göre gruplanmış boş
    odalardır; karara bağlanmış (reddedilen/iptal) başvurularda null gelir.
--}}
@if ($options === null)
    <span class="text-xs text-ink-subtle">—</span>
@else
    <form method="POST" action="{{ route('admin.reservations.assign-room', $reservation) }}"
          x-data="{ degisti: false }" class="flex items-center gap-1.5">
        @csrf
        <select name="room_id" @change="degisti = true"
                class="field-input !w-auto !min-w-[9rem] !py-1 text-xs">
            <option value="">Atanmadı</option>
            @if ($reservation->room)
                <option value="{{ $reservation->room->id }}" selected>{{ $reservation->room->label() }}</option>
            @endif
            @foreach ($options as $blok => $odalar)
                <optgroup label="{{ $blok }} — {{ $odalar->count() }} boş">
                    @foreach ($odalar as $oda)
                        @continue($reservation->room && $oda->id === $reservation->room->id)
                        <option value="{{ $oda->id }}">{{ $oda->label() }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        <button type="submit" x-show="degisti" x-cloak class="btn-primary !px-2.5 !py-1 text-xs">Ata</button>
    </form>
@endif
