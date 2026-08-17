<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FacilityController extends Controller
{
    public function index()
    {
        return view('admin.facilities.index', [
            'facilities' => Facility::ordered()
                ->with(['roomTypes' => fn ($q) => $q->ordered()])
                ->withCount('reservations')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validateWithBag('facility', [
            'name' => ['required', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ], [], ['name' => 'tesis adı']);

        Facility::create($data + [
            'slug' => Str::slug($data['name']),
            'sort_order' => (Facility::max('sort_order') ?? 0) + 1,
        ]);

        return back()->with('success', 'Tesis eklendi.');
    }

    public function update(Request $request, Facility $facility)
    {
        $data = $request->validateWithBag('facility-' . $facility->id, [
            'name' => ['required', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ], [], ['name' => 'tesis adı']);

        $facility->update($data + ['is_active' => (bool) ($data['is_active'] ?? false)]);

        return back()->with('success', "{$facility->name} güncellendi.");
    }

    public function storeRoomType(Request $request, Facility $facility)
    {
        $data = $this->roomTypeRules($request, $facility);

        RoomType::create($data + [
            'facility_id' => $facility->id,
            'code' => Str::slug($facility->slug . '-' . $data['name']),
            'sort_order' => (RoomType::where('facility_id', $facility->id)->max('sort_order') ?? 0) + 1,
        ]);

        return back()->with('success', 'Oda tipi eklendi.');
    }

    public function updateRoomType(Request $request, RoomType $roomType)
    {
        $data = $this->roomTypeRules($request, $roomType->facility, $roomType);

        $roomType->update($data);

        return back()->with('success', "{$roomType->name} güncellendi.");
    }

    /** @return array<string, mixed> */
    private function roomTypeRules(Request $request, Facility $facility, ?RoomType $roomType = null): array
    {
        $bag = $roomType ? 'room-type-' . $roomType->id : 'room-type-new-' . $facility->id;

        $data = $request->validateWithBag($bag, [
            'name' => ['required', 'string', 'max:120', Rule::unique('room_types', 'name')
                ->where('facility_id', $facility->id)
                ->ignore($roomType?->id)],
            'kind' => ['required', 'in:room,villa'],
            'bed_count' => ['required', 'integer', 'min:1', 'max:20'],
            'is_ground_floor' => ['nullable', 'boolean'],
            'min_billed_persons' => ['nullable', 'integer', 'min:1', 'max:20'],
            'max_persons' => ['nullable', 'integer', 'min:1', 'max:20'],
            'waive_empty_bed_at_occupancy' => ['nullable', 'integer', 'min:1', 'max:20'],
            // 0, envanterinde hiç oda kalmamış bir tipin geçerli durumudur.
            'quantity' => ['required', 'integer', 'min:0', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ], [], [
            'name' => 'oda tipi adı',
            'bed_count' => 'yatak sayısı',
            'quantity' => 'adet',
            'min_billed_persons' => 'asgari ücretlendirilen kişi',
            'max_persons' => 'azami kişi',
            'waive_empty_bed_at_occupancy' => 'boş yatak muafiyeti',
        ]);

        $data['is_ground_floor'] = (bool) ($data['is_ground_floor'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}
