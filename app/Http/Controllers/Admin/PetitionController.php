<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Petition;
use App\Support\SearchText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Üyelerden gelen dilekçelerin görüntülenmesi ve yanıtlanması. */
class PetitionController extends Controller
{
    public function index(Request $request)
    {
        $query = Petition::with(['user', 'reservation.facility', 'responder']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->get('q')) {
            $query->whereHas('user', function ($u) use ($search) {
                foreach (SearchText::tokens($search) as $kelime) {
                    $u->where('search_index', 'like', "%{$kelime}%");
                }
            });
        }

        return view('admin.petitions.index', [
            'petitions' => $query->latest()->paginate(20)->withQueryString(),
            'counts' => Petition::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    public function reply(Request $request, Petition $petition)
    {
        $data = $request->validate([
            'reply' => ['required', 'string', 'max:4000'],
            'status' => ['required', 'in:answered,closed'],
        ], [], ['reply' => 'yanıt']);

        $petition->update([
            'reply' => $data['reply'],
            'status' => $data['status'],
            'replied_by' => Auth::id(),
            'replied_at' => now(),
        ]);

        return back()->with('success', 'Dilekçe yanıtlandı.');
    }
}
