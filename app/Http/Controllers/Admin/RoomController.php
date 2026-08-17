<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Fiziksel oda envanteri — blok ve oda numarasıyla tanımlı tek tek odalar.
 *
 * Oda *tipleri* ve ücretlendirme FacilityController'da yönetilir; buradaki ekran
 * "MELİSA ZEMİN 7 bakımda mı" düzeyindeki soruya bakar.
 */
class RoomController extends Controller
{
    public function index(Request $request)
    {
        $facilities = Facility::ordered()
            ->withCount('rooms')
            ->get();

        $facility = $facilities->firstWhere('slug', $request->get('tesis'))
            ?? $facilities->firstWhere(fn (Facility $f) => $f->rooms_count > 0)
            ?? $facilities->first();

        $rooms = collect();
        $roomTypes = collect();

        if ($facility) {
            $query = Room::where('facility_id', $facility->id)->with('roomType');

            if ($request->get('durum') === 'passive') {
                $query->where('is_active', false);
            } elseif ($request->get('durum') === 'active') {
                $query->where('is_active', true);
            }

            if ($block = $request->get('blok')) {
                $query->where('block', $block);
            }

            if ($type = $request->get('tip')) {
                $query->where('room_type_id', $type);
            }

            $rooms = $query->ordered()->get();

            $roomTypes = RoomType::where('facility_id', $facility->id)
                ->where('kind', 'room')
                ->withCount([
                    'rooms',
                    'rooms as active_rooms_count' => fn ($q) => $q->where('is_active', true),
                ])
                ->ordered()
                ->get();
        }

        return view('admin.rooms.index', [
            'facilities' => $facilities,
            'facility' => $facility,
            'roomTypes' => $roomTypes,
            'blocks' => $rooms->groupBy('block'),
            // Filtre kutusu, seçili filtreden bağımsız olarak tüm blokları listeler.
            'allBlocks' => $facility
                ? Room::where('facility_id', $facility->id)->distinct()->orderBy('block')->pluck('block')
                : collect(),
            'totalRooms' => $rooms->count(),
        ]);
    }

    public function update(Request $request, Room $room)
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
            'room_type_id' => [
                'required',
                Rule::exists('room_types', 'id')->where('facility_id', $room->facility_id),
            ],
        ], [], [
            'is_active' => 'durum',
            'note' => 'not',
            'room_type_id' => 'oda tipi',
        ]);

        $room->update($data);

        // Oda tipi adedi fiziksel envanterden türediği için birlikte güncellenir.
        $this->syncQuantities($room->facility_id);

        return back()->with('success', "{$room->label()} güncellendi.");
    }

    private function syncQuantities(int $facilityId): void
    {
        $counts = Room::where('facility_id', $facilityId)
            ->where('is_active', true)
            ->selectRaw('room_type_id, COUNT(*) as total')
            ->groupBy('room_type_id')
            ->pluck('total', 'room_type_id');

        RoomType::where('facility_id', $facilityId)
            ->where('kind', 'room')
            ->get()
            ->each(fn (RoomType $type) => $type->update([
                'quantity' => (int) ($counts[$type->id] ?? 0),
            ]));
    }
}
