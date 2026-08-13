<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;

/**
 * Üyenin kendi aidat geçmişi. Tahsilat kaydı Dernek tarafından girilir;
 * bu ekran yalnızca bilgilendirir.
 */
class DuesController extends Controller
{
    public function index()
    {
        $member = Auth::user();
        $dues = $member->dues()->orderByDesc('year')->get();
        $outstanding = $member->outstandingDues();

        return view('customer.dues.index', [
            'member' => $member,
            'dues' => $dues,
            'outstanding' => $outstanding,
            'debtTotal' => (float) $outstanding->sum('amount'),
            'paidTotal' => (float) $dues->where('status', 'paid')->sum('amount'),
            'paidThrough' => $member->duesPaidThrough(),
            'hasDebt' => $member->hasDuesDebt(),
            'bankAccounts' => Setting::get('bank_accounts', []),
        ]);
    }
}
