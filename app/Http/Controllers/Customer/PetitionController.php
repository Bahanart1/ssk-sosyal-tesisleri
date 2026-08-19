<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Petition;
use App\Services\DocumentStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Üye dilekçeleri. Üye form doldurmaz: dilekçesini yazıp fotoğrafını (veya
 * PDF'ini) yükler; Dernek görseli açıp yanıtını yazar.
 */
class PetitionController extends Controller
{
    public function __construct(private readonly DocumentStorage $documents) {}

    public function index()
    {
        return view('customer.petitions.index', [
            'petitions' => Auth::user()->petitions()->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'attachment' => ['required', ...DocumentStorage::RULES],
        ], [
            'attachment.required' => 'Dilekçenizin görselini veya PDF dosyasını eklemeniz gerekir.',
        ], [
            'attachment' => 'dilekçe dosyası',
        ]);

        Petition::create([
            'user_id' => Auth::id(),
            'category' => 'other',
            'attachment_path' => $this->documents->store($request->file('attachment'), 'petitions', Auth::id()),
        ]);

        return redirect()->route('customer.petitions.index')
            ->with('success', 'Dilekçeniz iletildi. Yanıtı bu sayfadan takip edebilirsiniz.');
    }
}
