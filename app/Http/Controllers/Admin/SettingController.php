<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Yönetim Kurulunca her yıl belirlenen parametreler (Madde 8/1-2-4).
 */
class SettingController extends Controller
{
    public function index()
    {
        return view('admin.settings.index', [
            'deposits' => [
                'one_period' => Setting::number('deposit.one_period'),
                'two_periods' => Setting::number('deposit.two_periods'),
                'one_period_single' => Setting::number('deposit.one_period_single'),
                'two_periods_single' => Setting::number('deposit.two_periods_single'),
            ],
            'tiers' => Setting::get('surcharge.tiers', []),
            'rates' => [
                'child_meal' => Setting::number('child.free_meal_rate', 0.40),
                'child_discount' => Setting::number('child.discount_rate', 0.60),
                'ground_floor' => Setting::number('ground_floor.discount_rate', 0.10),
            ],
            'terms' => [
                'balance_due_days' => (int) Setting::number('balance.due_days', 15),
                'cancellation_min_days' => (int) Setting::number('cancellation.min_days_before', 10),
                'refund_cancellation_fee' => (float) Setting::number('refund.cancellation_fee', 0),
            ],
            'duesAmount' => Setting::number('dues.annual_amount', 0),
            'bankAccounts' => Setting::get('bank_accounts', []),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'deposit_one_period' => ['required', 'numeric', 'min:0'],
            'deposit_two_periods' => ['required', 'numeric', 'min:0'],
            'deposit_one_period_single' => ['required', 'numeric', 'min:0'],
            'deposit_two_periods_single' => ['required', 'numeric', 'min:0'],

            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*.from' => ['nullable', 'date'],
            'tiers.*.to' => ['nullable', 'date'],
            'tiers.*.amount' => ['required', 'numeric', 'min:0'],
            'tiers.*.label' => ['nullable', 'string', 'max:120'],

            'child_meal_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'child_discount_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'ground_floor_rate' => ['required', 'numeric', 'min:0', 'max:1'],

            'balance_due_days' => ['required', 'integer', 'min:1', 'max:120'],
            'cancellation_min_days' => ['required', 'integer', 'min:0', 'max:120'],
            'refund_cancellation_fee' => ['required', 'numeric', 'min:0'],

            'dues_annual_amount' => ['required', 'numeric', 'min:0'],

            'bank_accounts' => ['nullable', 'array'],
            'bank_accounts.*.bank' => ['required_with:bank_accounts.*.iban', 'nullable', 'string', 'max:120'],
            'bank_accounts.*.branch' => ['nullable', 'string', 'max:160'],
            'bank_accounts.*.iban' => ['nullable', 'string', 'max:40'],
        ], [], [
            'deposit_one_period' => 'bir devre peşinatı',
            'deposit_two_periods' => 'iki devre peşinatı',
            'child_meal_rate' => '0-5 yaş yemek oranı',
            'child_discount_rate' => '6-11 yaş oranı',
            'ground_floor_rate' => 'zemin kat indirimi',
            'balance_due_days' => 'bakiye ödeme süresi',
        ]);

        Setting::put('deposit.one_period', (float) $data['deposit_one_period'], 'pesinat');
        Setting::put('deposit.two_periods', (float) $data['deposit_two_periods'], 'pesinat');
        Setting::put('deposit.one_period_single', (float) $data['deposit_one_period_single'], 'pesinat');
        Setting::put('deposit.two_periods_single', (float) $data['deposit_two_periods_single'], 'pesinat');

        Setting::put('surcharge.tiers', array_values(array_map(fn ($t) => [
            'from' => $t['from'] ?: null,
            'to' => $t['to'] ?: null,
            'amount' => (float) $t['amount'],
            'label' => $t['label'] ?? null,
        ], $data['tiers'])), 'ucretlendirme');

        Setting::put('child.free_meal_rate', (float) $data['child_meal_rate'], 'ucretlendirme');
        Setting::put('child.discount_rate', (float) $data['child_discount_rate'], 'ucretlendirme');
        Setting::put('ground_floor.discount_rate', (float) $data['ground_floor_rate'], 'ucretlendirme');

        Setting::put('balance.due_days', (int) $data['balance_due_days'], 'odeme');
        Setting::put('cancellation.min_days_before', (int) $data['cancellation_min_days'], 'odeme');
        Setting::put('refund.cancellation_fee', (float) $data['refund_cancellation_fee'], 'odeme');
        Setting::put('dues.annual_amount', (float) $data['dues_annual_amount'], 'aidat');

        Setting::put('bank_accounts', array_values(array_filter(
            $data['bank_accounts'] ?? [],
            fn ($a) => filled($a['iban'] ?? null)
        )), 'odeme');

        return back()->with('success', 'Parametreler güncellendi.');
    }
}
