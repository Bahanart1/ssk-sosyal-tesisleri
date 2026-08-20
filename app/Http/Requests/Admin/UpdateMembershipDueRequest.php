<?php

namespace App\Http\Requests\Admin;

use App\Models\MembershipDue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMembershipDueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['unpaid', 'paid', 'waived'])],
            'paid_at' => ['nullable', 'date', 'required_if:status,paid'],
            'method' => ['nullable', Rule::in(array_keys(MembershipDue::METHODS)), 'required_if:status,paid'],
            'receipt_no' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'paid_at.required_if' => 'Ödendi olarak işaretlemek için ödeme tarihi gerekir.',
            'method.required_if' => 'Ödendi olarak işaretlemek için ödeme yöntemi gerekir.',
        ];
    }

    public function attributes(): array
    {
        return [
            'amount' => 'tutar',
            'paid_at' => 'ödeme tarihi',
            'method' => 'ödeme yöntemi',
            'receipt_no' => 'makbuz no',
        ];
    }
}
