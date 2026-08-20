<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTariffRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:160'],
            'scope' => ['required', 'in:room,villa'],
            'is_discounted' => ['nullable', 'boolean'],
            'empty_bed_fee' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'tarife adı', 'empty_bed_fee' => 'boş yatak ücreti'];
    }
}
