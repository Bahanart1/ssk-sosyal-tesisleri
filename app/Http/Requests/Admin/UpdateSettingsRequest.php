<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Peşinat, ücretlendirme, iptal ve aidat parametreleri. */
class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
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

            'cancellation_min_days' => ['required', 'integer', 'min:0', 'max:120'],
            'refund_cancellation_fee' => ['required', 'numeric', 'min:0'],

            'dues_annual_amount' => ['required', 'numeric', 'min:0'],
            'dues_late_fee_percent' => ['required', 'numeric', 'min:0', 'max:100'],

            'bank_accounts' => ['nullable', 'array'],
            'bank_accounts.*.bank' => ['required_with:bank_accounts.*.iban', 'nullable', 'string', 'max:120'],
            'bank_accounts.*.branch' => ['nullable', 'string', 'max:160'],
            'bank_accounts.*.iban' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function attributes(): array
    {
        return [
            'deposit_one_period' => 'bir devre peşinatı',
            'deposit_two_periods' => 'iki devre peşinatı',
            'child_meal_rate' => '0-5 yaş yemek oranı',
            'child_discount_rate' => '6-11 yaş oranı',
            'ground_floor_rate' => 'zemin kat indirimi',
        ];
    }
}
