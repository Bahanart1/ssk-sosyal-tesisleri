<?php

namespace App\Http\Requests\Customer;

use App\Models\Period;
use App\Models\ReservationGuest;
use App\Models\RoomType;
use App\Services\DocumentStorage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canApply();
    }

    public function rules(): array
    {
        return [
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'period_id' => ['required', 'integer', 'exists:periods,id'],
            'second_period_id' => ['nullable', 'integer', 'exists:periods,id', 'different:period_id'],

            'guests' => ['required', 'array', 'min:1', 'max:12'],
            'guests.*.full_name' => ['required', 'string', 'max:120'],
            'guests.*.tc_no' => ['required', 'digits:11'],
            'guests.*.birth_date' => ['required', 'date', 'before:today'],
            'guests.*.relation' => ['required', 'string', 'in:' . implode(',', array_keys(ReservationGuest::RELATIONS))],
            'guests.*.customer_group_id' => ['required', 'integer', 'exists:customer_groups,id'],
            'guests.*.wants_meal' => ['nullable', 'boolean'],
            'guests.*.document' => ['required', ...DocumentStorage::RULES],
            'guests.*.civil_registry' => ['required', ...DocumentStorage::RULES],

            'ground_floor_request' => ['nullable', 'boolean'],
            'ground_floor_note' => ['nullable', 'required_if_accepted:ground_floor_request', 'string', 'max:500'],
            'health_report' => ['nullable', ...DocumentStorage::RULES],

            'note' => ['nullable', 'string', 'max:1000'],

            'deposit_method' => ['required', 'in:card,bank_transfer'],
            'deposit_receipt' => ['required_if:deposit_method,bank_transfer', ...DocumentStorage::RULES],
        ];
    }

    public function attributes(): array
    {
        return [
            'room_type_id' => 'oda tipi',
            'period_id' => 'devre',
            'second_period_id' => 'ikinci devre',
            'guests' => 'kişi listesi',
            'guests.*.full_name' => 'ad soyad',
            'guests.*.tc_no' => 'TC kimlik numarası',
            'guests.*.birth_date' => 'doğum tarihi',
            'guests.*.relation' => 'yakınlık',
            'guests.*.customer_group_id' => 'müşteri grubu',
            'guests.*.document' => 'kimlik belgesi',
            'guests.*.civil_registry' => 'vukuatlı nüfus kayıt örneği',
            'ground_floor_note' => 'mazeret açıklaması',
            'health_report' => 'sağlık raporu',
            'deposit_method' => 'peşinat ödeme yöntemi',
            'deposit_receipt' => 'banka dekontu',
        ];
    }

    public function messages(): array
    {
        return [
            'guests.*.document.required' => 'Her kişi için geçerli bir kimlik belgesi eklenmesi zorunludur.',
            'guests.*.civil_registry.required' => 'Her kişi için vukuatlı nüfus kayıt örneği eklenmesi zorunludur.',
            'guests.*.tc_no.digits' => 'TC kimlik numarası 11 haneli olmalıdır.',
            'deposit_receipt.required_if' => 'Havale ile ödemede banka dekontunu eklemeniz gerekir.',
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
                $period = Period::find($this->input('period_id'));
                $second = $this->input('second_period_id') ? Period::find($this->input('second_period_id')) : null;

                if (! $roomType || ! $period) {
                    return;
                }

                if ($roomType->facility_id !== $period->facility_id) {
                    $validator->errors()->add('room_type_id', 'Seçilen oda tipi bu tesise ait değil.');
                }

                foreach (array_filter([$period, $second]) as $p) {
                    if (! $p->is_open) {
                        $validator->errors()->add('period_id', "{$p->label()} başvuruya kapalıdır.");
                    }

                    if ($p->isPast()) {
                        $validator->errors()->add('period_id', "{$p->label()} geçmiş bir tarihte kaldığı için seçilemez.");
                    }
                }

                if ($second && ! $period->canCombineWith($second)) {
                    $validator->errors()->add(
                        'second_period_id',
                        'Yalnızca birleşen devreler listesindeki ardışık iki devre birlikte seçilebilir.'
                    );
                }

                $guests = (array) $this->input('guests', []);
                $capacity = $roomType->capacity();

                if (count($guests) > $capacity) {
                    $validator->errors()->add(
                        'guests',
                        "{$roomType->name} için en fazla {$capacity} kişi seçebilirsiniz."
                    );
                }

                $tcNumbers = array_filter(array_column($guests, 'tc_no'));
                if (count($tcNumbers) !== count(array_unique($tcNumbers))) {
                    $validator->errors()->add('guests', 'Aynı TC kimlik numarası birden fazla kişi için girilemez.');
                }
            },
        ];
    }
}
