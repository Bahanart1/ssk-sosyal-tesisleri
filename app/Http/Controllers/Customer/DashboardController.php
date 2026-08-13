<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
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

        $active = $reservations
            ->whereIn('status', ['pending', 'approved', 'paid'])
            ->sortBy('start_date')
            ->first();

        $awaitingPayment = $reservations->where('status', 'approved')
            ->filter(fn ($r) => $r->balanceDue() > 0)
            ->sortBy('balance_due_date');

        $past = $reservations->whereIn('status', ['rejected', 'cancelled'])
            ->merge($reservations->where('status', 'paid')->filter(fn ($r) => $r->end_date->isPast()))
            ->sortByDesc('created_at')
            ->take(5);

        return view('customer.dashboard', [
            'user' => $user,
            'reservations' => $reservations,
            'active' => $active,
            'awaitingPayment' => $awaitingPayment,
            'past' => $past,
            'hasDuesDebt' => $user->hasDuesDebt(),
        ]);
    }
}
