<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CampWeek;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CampWeekController extends Controller
{
    public function index()
    {
        $weeks = CampWeek::upcomingWeeks(16, onlyOpen: false);

        return view('admin.camp-weeks.index', [
            'weeks' => $weeks,
            'campNights' => Reservation::CAMP_NIGHTS,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'week_start' => ['required', 'date'],
            'is_open' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $weekStart = Carbon::parse($data['week_start'])->startOfDay();

        if (! $weekStart->isMonday()) {
            return back()->withErrors(['week_start' => 'Kamp haftası Pazartesi ile başlamalıdır.']);
        }

        CampWeek::updateOrCreate(
            ['week_start' => $weekStart->toDateString()],
            [
                'is_open' => $request->boolean('is_open'),
                'note' => $data['note'] ?? null,
            ]
        );

        $status = $request->boolean('is_open') ? 'açıldı' : 'kapatıldı';

        return back()->with('success', $weekStart->translatedFormat('d M Y') . " kamp haftası {$status}.");
    }
}
