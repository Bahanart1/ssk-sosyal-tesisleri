<?php

namespace Database\Seeders;

use App\Models\CustomerGroup;
use App\Models\Payment;
use App\Models\Period;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Pricing\GuestInput;
use App\Services\Pricing\PricingInput;
use App\Services\Pricing\ReservationPricer;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Yönetim panelindeki grafiklerin dolu görünmesi için örnek başvurular üretir.
 *
 * Varsayılan seed'e dahil DEĞİLDİR; yalnızca tanıtım/deneme ortamında çalıştırılır:
 *
 *     php artisan db:seed --class=DemoReservationSeeder
 */
class DemoReservationSeeder extends Seeder
{
    public function run(ReservationPricer $pricer): void
    {
        $groups = CustomerGroup::pluck('id', 'code');
        $periods = Period::with(['roomTariff.prices', 'villaTariff.prices', 'facility'])->ordered()->get();
        $roomTypes = RoomType::with('facility')->active()->get()->groupBy('facility_id');

        $names = [
            'Ali Korkmaz', 'Hatice Aydın', 'Mustafa Şen', 'Emine Polat', 'Hüseyin Arslan',
            'Fatma Çetin', 'İbrahim Doğan', 'Şerife Kurt', 'Osman Yıldız', 'Havva Erdem',
            'Ramazan Aksoy', 'Zeliha Bulut', 'Kemal Özkan', 'Nurten Taş', 'Yusuf Güneş',
            'Sevim Koç', 'Halil Bozkurt', 'Meryem Avcı', 'Ergün Sarı', 'Nazlı Uçar',
        ];

        $year = (int) now()->year;
        $duesAmount = \App\Models\Setting::number('dues.annual_amount', 2500);
        $adminId = User::where('role', 'admin')->value('id');

        $customers = collect($names)->map(function (string $name, int $i) use ($groups, $year, $duesAmount, $adminId) {
            $customer = User::firstOrCreate(
                ['tc_no' => str_pad((string) (20000000000 + $i), 11, '0')],
                [
                    'name' => $name,
                    'membership_no' => 'U-' . (4000 + $i),
                    'phone' => '0555 ' . str_pad((string) (100 + $i), 3, '0') . ' 00 00',
                    'email' => \Illuminate\Support\Str::of($name)->slug('.') . '@example.com',
                    'joined_at' => Carbon::parse('2018-01-01')->addMonths($i * 4)->toDateString(),
                    'password' => Hash::make('musteri123'),
                    'role' => 'customer',
                    'customer_group_id' => $groups[['I', 'I', 'I', 'II', 'III'][$i % 5]],
                    'is_active' => true,
                ]
            );

            // Üye olanlara son üç yılın aidat tahakkuku; her yedinci üye borçlu kalır
            if ($customer->isMember()) {
                $paidThrough = $i % 7 === 0 ? $year - 2 : $year;

                for ($y = $year - 2; $y <= $year; $y++) {
                    $paid = $y <= $paidThrough;

                    \App\Models\MembershipDue::updateOrCreate(
                        ['user_id' => $customer->id, 'year' => $y],
                        [
                            'amount' => $duesAmount,
                            'status' => $paid ? 'paid' : 'unpaid',
                            'paid_at' => $paid ? "{$y}-0" . (($i % 8) + 1) . '-1' . (($i % 8) + 1) : null,
                            'method' => $paid ? ['bank_transfer', 'cash', 'card'][$i % 3] : null,
                            'recorded_by' => $adminId,
                        ]
                    );
                }
            }

            return $customer;
        });

        // Durumlar zamana yayılır: aksi halde tahsilat eğrisi yapay biçimde sıfıra iner.
        $cycle = ['paid', 'paid', 'approved', 'paid', 'pending', 'paid'];
        $statuses = array_map(
            fn (int $i) => $i % 12 === 11 ? ($i < 12 ? 'rejected' : 'cancelled') : $cycle[$i % 6],
            range(0, 23)
        );

        foreach ($statuses as $i => $status) {
            $customer = $customers[$i % $customers->count()];
            $period = $periods[($i * 3) % $periods->count()];
            $facilityRooms = $roomTypes[$period->facility_id];
            $roomType = $facilityRooms[($i * 2) % $facilityRooms->count()];

            // Müracaat tarihi: Şubat–Ağustos arasına yayılır
            $appliedAt = Carbon::parse('2026-02-10')->addDays($i * 8)->addHours(9 + $i % 8);
            if ($appliedAt->gt(now())) {
                $appliedAt = now()->copy()->subDays($i % 20);
            }

            $guests = $this->guestsFor($i, $customer, $groups, $roomType->capacity());

            try {
                $breakdown = $pricer->quote(new PricingInput(
                    roomType: $roomType,
                    period: $period,
                    secondPeriod: null,
                    guests: $guests,
                    applicationDate: $appliedAt,
                ));
            } catch (\Throwable) {
                continue;
            }

            $reservation = Reservation::create([
                'code' => (string) Str::uuid(),
                'user_id' => $customer->id,
                'facility_id' => $roomType->facility_id,
                'room_type_id' => $roomType->id,
                'period_id' => $period->id,
                'start_date' => $period->start_date,
                'end_date' => $period->end_date,
                'nights' => $breakdown->nights,
                'status' => $status,
                'application_date' => $appliedAt->toDateString(),
                'surcharge_per_person_day' => $breakdown->surchargePerPersonDay,
                'empty_bed_count' => $breakdown->emptyBedCount,
                'empty_bed_fee_per_day' => $breakdown->emptyBedFeePerDay,
                'empty_bed_total' => $breakdown->emptyBedTotal,
                'accommodation_total' => $breakdown->accommodationTotal,
                'total_price' => $breakdown->total,
                'deposit_amount' => $breakdown->depositAmount,
                'deposit_status' => in_array($status, ['paid', 'approved'], true) ? 'verified' : 'pending',
                'price_breakdown' => $breakdown->toArray(),
                'balance_due_date' => in_array($status, ['paid', 'approved'], true)
                    ? $pricer->balanceDueDate($appliedAt->copy()->addDays(6), $period->start_date)
                    : null,
                'decided_at' => in_array($status, ['pending'], true) ? null : $appliedAt->copy()->addDays(6),
                'created_at' => $appliedAt,
                'updated_at' => $appliedAt,
            ]);

            $reservation->update(['code' => '2026-' . str_pad((string) $reservation->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($guests as $order => $guest) {
                $line = $breakdown->guestLines[$order];

                $reservation->guests()->create([
                    'full_name' => $guest->name,
                    'tc_no' => str_pad((string) (30000000000 + $i * 10 + $order), 11, '0'),
                    'birth_date' => $guest->birthDate,
                    'relation' => $order === 0 ? 'self' : ($guest->wantsMeal || $line['age_category'] !== 'adult' ? 'child' : 'spouse'),
                    'customer_group_id' => $guest->customerGroupId,
                    'age_category' => $line['age_category'],
                    'wants_meal' => $guest->wantsMeal,
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                    'sort_order' => $order,
                ]);
            }

            $this->payments($reservation, $status, $appliedAt);
        }
    }

    /** @return list<GuestInput> */
    private function guestsFor(int $i, User $customer, $groups, int $capacity): array
    {
        $count = min($capacity, [1, 2, 2, 3, 4, 2, 5][$i % 7]);
        $guests = [];

        for ($g = 0; $g < $count; $g++) {
            // Her üçüncü başvuruda bir çocuk bulunsun
            $isChild = $g > 1 && $i % 3 === 0;
            $isInfant = $g > 2 && $i % 5 === 0;

            $birth = match (true) {
                $isInfant => Carbon::parse('2022-06-15'),
                $isChild => Carbon::parse('2017-04-20'),
                default => Carbon::parse('1980-01-01')->addYears($g * 3 + $i % 12),
            };

            $guests[] = new GuestInput(
                customerGroupId: $g === 0 ? $customer->customer_group_id : $groups[['I', 'I', 'II', 'III'][($i + $g) % 4]],
                birthDate: $birth,
                wantsMeal: $isInfant,
                name: $g === 0 ? $customer->name : Str::before($customer->name, ' ') . ' yakını ' . $g,
            );
        }

        return $guests;
    }

    private function payments(Reservation $reservation, string $status, Carbon $appliedAt): void
    {
        if (in_array($status, ['rejected', 'cancelled'], true)) {
            return;
        }

        // Ödeme tarihleri geleceğe taşmasın
        $depositPaidAt = min($appliedAt->copy()->addHours(4), now());

        Payment::create([
            'reservation_id' => $reservation->id,
            'kind' => 'deposit',
            'method' => $reservation->id % 3 === 0 ? 'card' : 'bank_transfer',
            'amount' => $reservation->deposit_amount,
            'status' => $status === 'pending' ? 'pending' : 'success',
            'reference_no' => Payment::newReference(),
            'paid_at' => $status === 'pending' ? null : $depositPaidAt,
            'created_at' => $depositPaidAt,
            'updated_at' => $depositPaidAt,
        ]);

        if ($status !== 'paid') {
            return;
        }

        $balancePaidAt = min($appliedAt->copy()->addDays(9), now());

        Payment::create([
            'reservation_id' => $reservation->id,
            'kind' => 'balance',
            'method' => 'card',
            'amount' => max(0, (float) $reservation->total_price - (float) $reservation->deposit_amount),
            'installment' => [1, 1, 3, 6][$reservation->id % 4],
            'status' => 'success',
            'reference_no' => Payment::newReference(),
            'paid_at' => $balancePaidAt,
            'created_at' => $balancePaidAt,
            'updated_at' => $balancePaidAt,
        ]);
    }
}
