<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'pending' => Reservation::where('status', 'pending')->count(),
            'approved' => Reservation::where('status', 'approved')->count(),
            'paid' => Reservation::where('status', 'paid')->count(),
            'customers' => User::where('role', 'customer')->count(),
            'collected' => (float) Payment::where('status', 'success')->sum('amount'),
            'awaiting_receipts' => Payment::where('status', 'pending')->where('method', 'bank_transfer')->count(),
        ];

        $recent = Reservation::with(['user', 'facility', 'roomType', 'period'])
            ->latest()
            ->take(8)
            ->get();

        $pendingReceipts = Payment::with('reservation.user')
            ->where('status', 'pending')
            ->where('method', 'bank_transfer')
            ->latest()
            ->take(6)
            ->get();

        // Devre bazlı doluluk: yer tahsisi otomatik yapılmaz, karar yöneticinindir (Madde 6/1).
        $occupancy = Facility::active()->ordered()
            ->with(['periods' => fn ($q) => $q->open()->upcoming()->ordered()->limit(6)])
            ->get()
            ->map(fn (Facility $f) => [
                'facility' => $f,
                'periods' => $f->periods->map(fn ($p) => [
                    'period' => $p,
                    'reservations' => Reservation::whereIn('status', ['pending', 'approved', 'paid'])
                        ->where(fn ($q) => $q->where('period_id', $p->id)->orWhere('second_period_id', $p->id))
                        ->count(),
                ]),
            ]);

        return view('admin.dashboard', compact('stats', 'recent', 'pendingReceipts', 'occupancy'));
    }
}
