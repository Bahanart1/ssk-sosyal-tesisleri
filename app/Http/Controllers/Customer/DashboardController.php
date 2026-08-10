<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user()->load('customerClass');

        $reservations = $user->reservations()
            ->with(['facility', 'customerClass', 'payment'])
            ->latest('check_in')
            ->get();

        $current = $reservations->firstWhere('status', 'approved')
            ?? $reservations->firstWhere('status', 'paid');

        $upcoming = $reservations->where('check_in', '>=', now()->startOfDay())
            ->whereIn('status', ['approved', 'paid', 'pending'])
            ->sortBy('check_in')
            ->first();

        $previous = $reservations->whereIn('status', ['paid', 'cancelled', 'rejected'])
            ->where('check_out', '<', now())
            ->take(5);

        return view('customer.dashboard', [
            'user' => $user,
            'reservations' => $reservations,
            'current' => $current,
            'upcoming' => $upcoming,
            'previous' => $previous,
        ]);
    }
}
