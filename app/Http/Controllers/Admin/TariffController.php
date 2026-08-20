<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTariffRequest;
use App\Http\Requests\Admin\UpdateTariffRequest;
use App\Models\CustomerGroup;
use App\Models\Facility;
use App\Models\Tariff;
use App\Models\TariffPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ücret tablolarının yönetimi.
 * Tablo 1 (scope=room) kişi başı günlük oda ücretleri,
 * Tablo 2 (scope=villa) Çolaklı villalarının yemeksiz ücretleridir.
 */
class TariffController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->get('year', now()->year);

        return view('admin.tariffs.index', [
            'year' => $year,
            'years' => Tariff::distinct()->orderBy('year')->pluck('year'),
            'groups' => CustomerGroup::ordered()->get(),
            'facilities' => Facility::ordered()
                ->with(['tariffs' => fn ($q) => $q->where('year', $year)->with('prices')->ordered()])
                ->get(),
        ]);
    }

    public function store(StoreTariffRequest $request)
    {
        $data = $request->validated();

        $tariff = Tariff::create($data + ['sort_order' => 99]);

        // Her müşteri grubu için sıfır fiyatla satır açılır; yönetici doldurur.
        foreach (CustomerGroup::ordered()->get() as $group) {
            TariffPrice::create([
                'tariff_id' => $tariff->id,
                'customer_group_id' => $group->id,
                'adult_price' => 0,
            ]);
        }

        return back()->with('success', 'Tarife eklendi. Şimdi grup ücretlerini girebilirsiniz.');
    }

    public function update(UpdateTariffRequest $request, Tariff $tariff)
    {
        $data = $request->validated();

        DB::transaction(function () use ($tariff, $data) {
            $tariff->update([
                'name' => $data['name'],
                'is_discounted' => (bool) ($data['is_discounted'] ?? false),
                // Boş bırakılırsa "Alınmaz" anlamına gelir (Madde 8/9).
                'empty_bed_fee' => $data['empty_bed_fee'] === null || $data['empty_bed_fee'] === ''
                    ? null
                    : $data['empty_bed_fee'],
            ]);

            foreach ($data['prices'] as $groupId => $price) {
                TariffPrice::updateOrCreate(
                    ['tariff_id' => $tariff->id, 'customer_group_id' => (int) $groupId],
                    [
                        'adult_price' => $price['adult_price'],
                        'child_price' => $price['child_price'] !== null && $price['child_price'] !== '' ? $price['child_price'] : null,
                        'min_daily_total' => $price['min_daily_total'] !== null && $price['min_daily_total'] !== '' ? $price['min_daily_total'] : null,
                    ]
                );
            }
        });

        return back()->with('success', "{$tariff->name} güncellendi.");
    }
}
