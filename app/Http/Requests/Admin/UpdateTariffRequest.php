<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTariffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'is_discounted' => ['nullable', 'boolean'],
            'empty_bed_fee' => ['nullable', 'numeric', 'min:0'],
            'prices' => ['required', 'array'],
            'prices.*.adult_price' => ['required', 'numeric', 'min:0'],
            'prices.*.child_price' => ['nullable', 'numeric', 'min:0'],
            'prices.*.min_daily_total' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'tarife adı',
            'empty_bed_fee' => 'boş yatak ücreti',
            'prices.*.adult_price' => '12 yaş üstü ücreti',
        ];
    }
}
