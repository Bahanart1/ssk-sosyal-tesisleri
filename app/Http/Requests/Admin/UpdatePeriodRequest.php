<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'nights' => ['required', 'integer', 'min:1', 'max:30'],
            'is_discounted' => ['nullable', 'boolean'],
            'is_open' => ['nullable', 'boolean'],
            'combine_group' => ['nullable', 'integer', 'min:1'],
            'room_tariff_id' => ['required', 'exists:tariffs,id'],
            'villa_tariff_id' => ['nullable', 'exists:tariffs,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'start_date' => 'başlangıç tarihi',
            'room_tariff_id' => 'oda tarifesi',
        ];
    }
}
