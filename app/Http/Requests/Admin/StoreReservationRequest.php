<?php

namespace App\Http\Requests\Admin;

use App\Models\ReservationGuest;
use App\Models\RoomType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Yöneticinin üye adına başvuru oluşturması. */
class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'period_id' => ['required', 'integer', 'exists:periods,id'],
            'second_period_id' => ['nullable', 'integer', 'exists:periods,id', 'different:period_id'],
            'guests' => ['required', 'array', 'min:1'],
            'guests.*.full_name' => ['required', 'string', 'max:120'],
            'guests.*.tc_no' => ['required', 'digits:11'],
            'guests.*.birth_date' => ['required', 'date', 'before:today'],
            'guests.*.relation' => ['required', 'string', 'in:'.implode(',', array_keys(ReservationGuest::RELATIONS))],
            'guests.*.customer_group_id' => ['required', 'integer', 'exists:customer_groups,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'üye',
            'room_type_id' => 'oda tipi',
            'period_id' => 'devre',
            'guests.*.full_name' => 'ad soyad',
            'guests.*.tc_no' => 'TC kimlik numarası',
            'guests.*.birth_date' => 'doğum tarihi',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $roomType = RoomType::find($this->input('room_type_id'));

                if (! $roomType) {
                    return;
                }

                $kapasite = $roomType->capacity();

                if (count((array) $this->input('guests', [])) > $kapasite) {
                    $validator->errors()->add(
                        'guests',
                        "{$roomType->name} için en fazla {$kapasite} kişi seçilebilir."
                    );
                }
            },
        ];
    }
}
