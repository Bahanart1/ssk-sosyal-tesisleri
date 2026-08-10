<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class CampWeek extends Model
{
    protected $fillable = ['week_start', 'is_open', 'note'];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'is_open' => 'boolean',
        ];
    }

    public static function isOpen(CarbonInterface $weekStart): bool
    {
        $row = static::query()
            ->whereDate('week_start', $weekStart->toDateString())
            ->first();

        // Kayıt yoksa varsayılan açık
        return $row ? $row->is_open : true;
    }

    /**
     * @return list<array{check_in: string, check_out: string, label: string, range: string, week_no: int, year: int, is_open: bool, note: ?string}>
     */
    public static function upcomingWeeks(int $count = 16, bool $onlyOpen = false): array
    {
        $start = now()->startOfWeek(Carbon::MONDAY)->startOfDay();

        if ($start->lt(now()->startOfDay())) {
            $start->addWeek();
        }

        $dates = collect();
        for ($i = 0; $i < $count; $i++) {
            $dates->push($start->copy()->addWeeks($i)->toDateString());
        }

        $rows = static::query()
            ->whereIn('week_start', $dates)
            ->get()
            ->keyBy(fn ($r) => $r->week_start->toDateString());

        $weeks = [];

        foreach ($dates as $date) {
            $checkIn = Carbon::parse($date)->startOfDay();
            $checkOut = Reservation::campCheckOut($checkIn);
            $row = $rows->get($date);
            $isOpen = $row ? $row->is_open : true;

            if ($onlyOpen && ! $isOpen) {
                continue;
            }

            $weeks[] = [
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'label' => $checkIn->translatedFormat('d M') . ' – ' . $checkOut->translatedFormat('d M Y'),
                'range' => $checkIn->translatedFormat('d M Y') . ' giriş · ' . $checkOut->translatedFormat('d M Y') . ' çıkış',
                'week_no' => $checkIn->isoWeek,
                'year' => $checkIn->isoWeekYear,
                'is_open' => $isOpen,
                'note' => $row?->note,
            ];
        }

        return $weeks;
    }
}
