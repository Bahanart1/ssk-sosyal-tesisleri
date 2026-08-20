<?php

namespace App\Http\Requests\Admin;

use App\Models\Period;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\RoomType;
use App\Services\DocumentStorage;
use App\Support\ReservationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Yöneticinin başvuru düzenlemesi.
 *
 * Ödemesi tamamlanmış rezervasyonlar da düzenlenebilir: üyeler telefonla kişi
 * ekletip çıkarttırabiliyor, tutar farkı sonradan tahsil ya da iade ediliyor.
 * İptal edilmiş başvuru düzenlenemez.
 */
class UpdateReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->reservation()->status !== ReservationStatus::CANCELLED;
    }

    /**
     * Yetki reddi 403 değil 422 döner: kural bir yetki eksikliği değil, kaydın
     * düzenlenemez durumda olmasıdır. Refactoring öncesi davranış korunuyor.
     */
    protected function failedAuthorization(): void
    {
        abort(422);
    }

    public function rules(): array
    {
        return [
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'period_id' => ['required', 'integer', 'exists:periods,id'],
            'second_period_id' => ['nullable', 'integer', 'exists:periods,id', 'different:period_id'],

            'guests' => ['required', 'array', 'min:1'],
            'guests.*.id' => ['nullable', 'integer'],
            'guests.*.full_name' => ['required', 'string', 'max:120'],
            'guests.*.tc_no' => ['required', 'digits:11'],
            'guests.*.birth_date' => ['required', 'date', 'before:today'],
            'guests.*.relation' => ['required', 'string', 'in:'.implode(',', array_keys(ReservationGuest::RELATIONS))],
            'guests.*.customer_group_id' => ['required', 'integer', 'exists:customer_groups,id'],
            'guests.*.wants_meal' => ['nullable', 'boolean'],
            'guests.*.document' => ['nullable', ...DocumentStorage::RULES],
            'guests.*.civil_registry' => ['nullable', ...DocumentStorage::RULES],

            // Ücret sistem tarafından hesaplanır; bu alanlar yalnızca istisnai
            // durumda elle girilir ve boş gelirse mevcut değer korunur.
            'empty_bed_count' => ['nullable', 'integer', 'min:0', 'max:10'],
            'surcharge_per_person_day' => ['nullable', 'numeric', 'min:0'],
            'adjustment_amount' => ['nullable', 'numeric'],
            'adjustment_note' => ['nullable', 'string', 'max:255'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
            'action' => ['required', 'in:save,approve,send_payment'],
        ];
    }

    public function attributes(): array
    {
        return [
            'room_type_id' => 'oda tipi',
            'period_id' => 'devre',
            'guests.*.full_name' => 'ad soyad',
            'guests.*.tc_no' => 'TC kimlik numarası',
            'guests.*.birth_date' => 'doğum tarihi',
            'adjustment_amount' => 'düzeltme tutarı',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $roomType = $this->roomType();
                $period = $this->period();
                $second = $this->secondPeriod();

                if (! $roomType || ! $period) {
                    return;
                }

                if ($second && ! $period->canCombineWith($second)) {
                    $validator->errors()->add(
                        'second_period_id',
                        'Yalnızca birleşen devreler listesindeki ardışık iki devre birlikte seçilebilir.'
                    );
                }

                // İkinci oda tahsis edilmişse kapasite iki katına çıkar.
                $reservation = $this->reservation();
                $kapasite = $roomType->capacity() * ($reservation->second_room_id ? 2 : 1);

                if (count((array) $this->input('guests', [])) > $kapasite) {
                    $validator->errors()->add('guests', $reservation->second_room_id
                        ? "İki {$roomType->name} için en fazla {$kapasite} kişi seçilebilir."
                        : "{$roomType->name} için en fazla {$kapasite} kişi seçilebilir. Daha fazlası için önce ikinci oda tahsis edin.");
                }
            },
        ];
    }

    public function reservation(): Reservation
    {
        return $this->route('reservation');
    }

    public function roomType(): ?RoomType
    {
        return RoomType::find($this->input('room_type_id'));
    }

    public function period(): ?Period
    {
        return Period::find($this->input('period_id'));
    }

    public function secondPeriod(): ?Period
    {
        return $this->input('second_period_id')
            ? Period::find($this->input('second_period_id'))
            : null;
    }
}
