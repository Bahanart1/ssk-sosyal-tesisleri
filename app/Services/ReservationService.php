<?php

namespace App\Services;

use App\Models\Period;
use App\Models\CustomerGroup;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Pricing\GuestInput;
use App\Services\Pricing\PriceBreakdown;
use App\Services\Pricing\PricingInput;
use App\Services\Pricing\ReservationPricer;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReservationService
{
    public function __construct(
        private readonly ReservationPricer $pricer,
        private readonly DocumentStorage $documents,
        private readonly RefundService $refunds,
    ) {}

    /**
     * Başvurudaki en yüksek müşteri grubu. Gruplar sort_order ile sıralıdır
     * (1 = I. Grup en üsttür); listede gruba göre süzmek için kullanılır.
     */
    private function topCustomerGroupId(Reservation $reservation): ?int
    {
        return CustomerGroup::whereIn('id', $reservation->guests()->pluck('customer_group_id'))
            ->orderBy('sort_order')
            ->value('id');
    }

    /**
     * Sihirbazdan gelen ham veriden fiyatlandırma girdisi kurar.
     *
     * @param  array{room_type_id:int, period_id:int, second_period_id?:int|null, guests:array<int, array<string, mixed>>}  $data
     */
    public function buildPricingInput(
        array $data,
        ?CarbonInterface $applicationDate = null,
        ?float $surchargeOverride = null,
        ?int $emptyBedOverride = null,
        float $adjustmentAmount = 0.0,
    ): PricingInput {
        $roomType = RoomType::with('facility')->findOrFail($data['room_type_id']);
        $period = Period::findOrFail($data['period_id']);
        $second = ! empty($data['second_period_id']) ? Period::findOrFail($data['second_period_id']) : null;

        $guests = [];
        foreach (array_values($data['guests']) as $index => $guest) {
            $guests[] = new GuestInput(
                customerGroupId: (int) $guest['customer_group_id'],
                birthDate: Carbon::parse($guest['birth_date']),
                wantsMeal: (bool) ($guest['wants_meal'] ?? false),
                name: $guest['full_name'] ?? null,
                key: $guest['id'] ?? $index,
            );
        }

        return new PricingInput(
            roomType: $roomType,
            period: $period,
            secondPeriod: $second,
            guests: $guests,
            applicationDate: $applicationDate ?? now(),
            surchargeOverride: $surchargeOverride,
            emptyBedOverride: $emptyBedOverride,
            adjustmentAmount: $adjustmentAmount,
        );
    }

    public function quote(PricingInput $input): PriceBreakdown
    {
        return $this->pricer->quote($input);
    }

    /**
     * Müşteri başvurusunu oluşturur.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $documents  Kişi indeksine göre kimlik belgeleri
     */
    /**
     * @param  array<int, UploadedFile>  $documents  Kişi sırasına göre kimlik belgeleri
     * @param  array<int, UploadedFile>  $registries Kişi sırasına göre vukuatlı nüfus kayıtları
     */
    public function create(
        User $user,
        array $data,
        array $documents,
        ?UploadedFile $healthReport = null,
        array $registries = [],
    ): Reservation {
        $input = $this->buildPricingInput($data, applicationDate: now());
        $breakdown = $this->quote($input);

        return DB::transaction(function () use ($user, $data, $documents, $healthReport, $registries, $input, $breakdown) {
            $reservation = Reservation::create([
                'code' => (string) Str::uuid(),
                'user_id' => $user->id,
                'facility_id' => $input->roomType->facility_id,
                'room_type_id' => $input->roomType->id,
                'period_id' => $input->period->id,
                'second_period_id' => $input->secondPeriod?->id,
                'start_date' => $input->startDate(),
                'end_date' => $input->endDate(),
                'nights' => $breakdown->nights,
                'status' => 'pending',
                'ground_floor_request' => (bool) ($data['ground_floor_request'] ?? false),
                'ground_floor_note' => $data['ground_floor_note'] ?? null,
                'health_report_path' => $healthReport
                    ? $this->documents->store($healthReport, DocumentStorage::HEALTH_REPORT, $user->id)
                    : null,
                'application_date' => now()->toDateString(),
                'note' => $data['note'] ?? null,
            ]);

            $reservation->update([
                'code' => now()->year . '-' . str_pad((string) $reservation->id, 6, '0', STR_PAD_LEFT),
            ]);

            foreach (array_values($data['guests']) as $index => $guest) {
                ReservationGuest::create([
                    'reservation_id' => $reservation->id,
                    'full_name' => $guest['full_name'],
                    'tc_no' => $guest['tc_no'],
                    'birth_date' => $guest['birth_date'],
                    'relation' => $guest['relation'],
                    'customer_group_id' => $guest['customer_group_id'],
                    'age_category' => $breakdown->guestLines[$index]['age_category'],
                    'wants_meal' => (bool) ($guest['wants_meal'] ?? false),
                    'id_document_path' => isset($documents[$index])
                        ? $this->documents->store($documents[$index], DocumentStorage::IDENTITY, $user->id)
                        : null,
                    'civil_registry_path' => isset($registries[$index])
                        ? $this->documents->store($registries[$index], DocumentStorage::CIVIL_REGISTRY, $user->id)
                        : null,
                    'sort_order' => $index,
                ]);
            }

            $this->applyBreakdown($reservation, $breakdown);

            return $reservation->fresh(['guests', 'roomType', 'period', 'secondPeriod', 'facility']);
        });
    }

    /**
     * Rezervasyonu mevcut kişileri ve seçimleriyle yeniden fiyatlandırır.
     * Admin düzenleme ekranı hem önizleme hem kayıt için bunu kullanır.
     */
    public function repriceExisting(Reservation $reservation, array $overrides = []): PriceBreakdown
    {
        $reservation->loadMissing(['guests', 'roomType.facility', 'period', 'secondPeriod']);

        $input = new PricingInput(
            roomType: $overrides['room_type'] ?? $reservation->roomType,
            period: $overrides['period'] ?? $reservation->period,
            secondPeriod: array_key_exists('second_period', $overrides)
                ? $overrides['second_period']
                : $reservation->secondPeriod,
            guests: $reservation->guests->map(fn (ReservationGuest $g) => new GuestInput(
                customerGroupId: $g->customer_group_id,
                birthDate: $g->birth_date,
                wantsMeal: $g->wants_meal,
                name: $g->full_name,
                key: $g->id,
            ))->values()->all(),
            applicationDate: $reservation->application_date,
            surchargeOverride: $overrides['surcharge'] ?? null,
            emptyBedOverride: $overrides['empty_bed_count'] ?? null,
            adjustmentAmount: $overrides['adjustment_amount'] ?? (float) $reservation->adjustment_amount,
        );

        return $this->quote($input);
    }

    /**
     * Hesaplanan dökümü rezervasyona ve kişilere yazar (snapshot).
     */
    public function applyBreakdown(Reservation $reservation, PriceBreakdown $breakdown): void
    {
        $reservation->update([
            'top_customer_group_id' => $this->topCustomerGroupId($reservation),
            'nights' => $breakdown->nights,
            'surcharge_per_person_day' => $breakdown->surchargePerPersonDay,
            'empty_bed_count' => $breakdown->emptyBedCount,
            'empty_bed_fee_per_day' => $breakdown->emptyBedFeePerDay,
            'empty_bed_total' => $breakdown->emptyBedTotal,
            'accommodation_total' => $breakdown->accommodationTotal,
            'adjustment_amount' => $breakdown->adjustmentAmount,
            'total_price' => $breakdown->total,
            'deposit_amount' => $breakdown->depositAmount,
            'price_breakdown' => $breakdown->toArray(),
        ]);

        $guests = $reservation->guests()->orderBy('sort_order')->get();

        foreach ($breakdown->guestLines as $index => $line) {
            $guest = $guests[$index] ?? null;

            $guest?->update([
                'age_category' => $line['age_category'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
            ]);
        }
    }

    /**
     * Yer tahsisi: rezervasyonu onaylayıp bakiye ödemesine açar (Madde 6/7, 8/8).
     */
    public function approve(Reservation $reservation, User $admin, ?string $note = null): Reservation
    {
        $breakdown = $this->repriceExisting($reservation, [
            'empty_bed_count' => $reservation->empty_bed_count,
            'adjustment_amount' => (float) $reservation->adjustment_amount,
            'surcharge' => (float) $reservation->surcharge_per_person_day,
        ]);

        $this->applyBreakdown($reservation, $breakdown);

        $reservation->update([
            'status' => 'approved',
            'admin_note' => $note ?: $reservation->admin_note,
            'decided_at' => now(),
            'approved_by' => $admin->id,
        ]);

        return $reservation->fresh();
    }

    public function reject(Reservation $reservation, User $admin, string $note): Reservation
    {
        $reservation->update([
            'status' => 'rejected',
            'admin_note' => $note,
            'decided_at' => now(),
            'approved_by' => $admin->id,
        ]);

        // Reddedilen başvurunun peşinat iadesi kendiliğinden açılır: yönetici
        // İadeler → Peşinatlar sayfasında görür, üye IBAN'ını bildirir.
        // Üye iptalinde ise kayıt üye talep gönderince açılır.
        $this->refunds->open($reservation, 'rejected');

        return $reservation->fresh();
    }
}
