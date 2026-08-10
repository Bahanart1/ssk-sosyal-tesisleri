<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total' => Reservation::count(),
            'pending' => Reservation::where('status', 'pending')->count(),
            'approved' => Reservation::where('status', 'approved')->count(),
            'paid' => Reservation::where('status', 'paid')->count(),
            'customers' => User::where('role', 'customer')->count(),
        ];

        $recent = Reservation::with(['user', 'facility', 'customerClass'])
            ->latest()
            ->take(8)
            ->get();

        return view('admin.dashboard', compact('stats', 'recent'));
    }
}
