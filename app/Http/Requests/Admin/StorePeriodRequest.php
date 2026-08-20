<?php

namespace App\Http\Requests\Admin;

use App\Models\Period;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'exists:facilities,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'number' => ['required', 'integer', 'min:1', 'max:60'],
            'start_date' => ['required', 'date'],
            'nights' => ['required', 'integer', 'min:1', 'max:30'],
            'is_discounted' => ['nullable', 'boolean'],
            'combine_group' => ['nullable', 'integer', 'min:1'],
            'room_tariff_id' => ['required', 'exists:tariffs,id'],
            'villa_tariff_id' => ['nullable', 'exists:tariffs,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'number' => 'devre no',
            'start_date' => 'başlangıç tarihi',
            'room_tariff_id' => 'oda tarifesi',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $varMi = Period::where('facility_id', $this->input('facility_id'))
                    ->where('year', $this->input('year'))
                    ->where('number', $this->input('number'))
                    ->exists();

                if ($varMi) {
                    $validator->errors()->add('number', 'Bu tesis ve yıl için aynı numaralı devre zaten tanımlı.');
                }
            },
        ];
    }
}
