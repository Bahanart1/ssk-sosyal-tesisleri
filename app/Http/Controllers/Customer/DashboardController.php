<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user()->load('customerGroup');

        $reservations = $user->reservations()
            ->with(['facility', 'roomType', 'period', 'secondPeriod', 'payments'])
            ->latest('created_at')
            ->get();

        // Yaklaşan konaklama: yer tahsis edilmiş ve devresi henüz bitmemiş
        $upcoming = $reservations
            ->whereIn('status', ['approved', 'paid'])
            ->filter(fn ($r) => $r->end_date->gte(now()->startOfDay()))
            ->sortBy('start_date')
            ->first();

        $awaitingPayment = $reservations
            ->where('status', 'approved')
            ->filter(fn ($r) => $r->balanceDue() > 0)
            ->sortBy('balance_due_date');

        return view('customer.dashboard', [
            'user' => $user,
            'recent' => $reservations->take(3),
            'total' => $reservations->count(),
            'pendingCount' => $reservations->where('status', 'pending')->count(),
            'upcoming' => $upcoming,
            'awaitingPayment' => $awaitingPayment,
            'balanceTotal' => (float) $awaitingPayment->sum(fn ($r) => $r->balanceDue()),
            'hasDuesDebt' => $user->hasDuesDebt(),
            'outstandingDues' => $user->outstandingDues(),
            'duesDebtTotal' => $user->duesDebtTotal(),
            'canApply' => $user->canApply(),
            'facilities' => Facility::active()->ordered()->withCount(['periods as open_periods_count' => fn ($q) => $q->open()->upcoming()])->get(),
        ]);
    }
}
