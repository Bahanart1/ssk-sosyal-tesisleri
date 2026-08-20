<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\Setting;

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
                'cancellation_min_days' => (int) Setting::number('cancellation.min_days_before', 10),
                'refund_cancellation_fee' => (float) Setting::number('refund.cancellation_fee', 0),
            ],
            'duesAmount' => Setting::number('dues.annual_amount', 0),
            'duesLateFeePercent' => Setting::number('dues.late_fee_monthly_percent', 0),
            'bankAccounts' => Setting::get('bank_accounts', []),
        ]);
    }

    public function update(UpdateSettingsRequest $request)
    {
        $data = $request->validated();

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

        Setting::put('cancellation.min_days_before', (int) $data['cancellation_min_days'], 'odeme');
        Setting::put('refund.cancellation_fee', (float) $data['refund_cancellation_fee'], 'odeme');
        Setting::put('dues.annual_amount', (float) $data['dues_annual_amount'], 'aidat');
        Setting::put('dues.late_fee_monthly_percent', (float) $data['dues_late_fee_percent'], 'aidat');

        Setting::put('bank_accounts', array_values(array_filter(
            $data['bank_accounts'] ?? [],
            fn ($a) => filled($a['iban'] ?? null)
        )), 'odeme');

        return back()->with('success', 'Parametreler güncellendi.');
    }
}
