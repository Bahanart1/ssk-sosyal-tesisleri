<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Rules\Iban;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Üyenin iade hesabını bildirmesi. IBAN yalnızca iade gerektiğinde istenir;
 * tüm üyelerden peşinen hesap bilgisi toplanmaz.
 */
class RefundController extends Controller
{
    public function __construct(private readonly RefundService $refunds) {}

    public function update(Request $request, Refund $refund)
    {
        abort_unless($refund->user_id === Auth::id(), 403);
        abort_if($refund->isPaid(), 422, 'Bu iade zaten ödendi.');

        $data = $request->validate([
            'iban' => ['required', 'string', 'max:34', new Iban],
            'account_holder' => ['required', 'string', 'max:120'],
        ], [], [
            'iban' => 'IBAN',
            'account_holder' => 'hesap sahibi',
        ]);

        $this->refunds->submitAccount($refund, $data['iban'], $data['account_holder']);

        return back()->with('success', 'Hesap bilgileriniz alındı. İade Dernek tarafından bu hesaba yapılacaktır.');
    }
}
