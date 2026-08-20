<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\MembershipDue;
use App\Models\Setting;
use App\Services\DocumentStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Üyenin kendi aidat geçmişi. Tahsilat kaydı Dernek tarafından girilir;
 * bu ekran yalnızca bilgilendirir.
 */
class DuesController extends Controller
{
    public function __construct(private readonly DocumentStorage $documents) {}

    public function index()
    {
        $member = Auth::user();
        $dues = $member->dues()->orderByDesc('year')->get();
        $outstanding = $member->outstandingDues();

        return view('customer.dues.index', [
            'member' => $member,
            'dues' => $dues,
            'outstanding' => $outstanding,
            'debtTotal' => round((float) $outstanding->sum(fn ($d) => $d->totalDue()), 2),
            'interestTotal' => round((float) $outstanding->sum(fn ($d) => $d->interestAmount()), 2),
            'paidTotal' => (float) $dues->where('status', 'paid')->sum('amount'),
            'paidThrough' => $member->duesPaidThrough(),
            'hasDebt' => $member->hasDuesDebt(),
            'bankAccounts' => Setting::get('bank_accounts', []),
        ]);
    }

    /** Üye borçlu yıl için havale yapar ve dekontunu yükler; onay yönetimde. */
    public function payTransfer(Request $request, MembershipDue $due)
    {
        $this->authorize('act', $due);

        if ($due->status !== 'unpaid') {
            return back()->with('error', 'Bu yılın aidatı için bekleyen bir ödeme zaten var ya da aidat ödenmiş.');
        }

        $request->validate([
            'receipt' => ['required', ...DocumentStorage::RULES],
        ], [
            'receipt.required' => 'Banka dekontunuzu eklemeniz gerekir.',
        ], ['receipt' => 'banka dekontu']);

        $due->update([
            'status' => 'review',
            'method' => 'bank_transfer',
            'receipt_path' => $this->documents->store($request->file('receipt'), 'dues-receipts', Auth::id()),
        ]);

        return back()->with('success', "{$due->year} aidat dekontunuz alındı. Yönetim onayladığında ödendi olarak görünecek.");
    }
}
