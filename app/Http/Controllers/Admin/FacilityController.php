<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use Illuminate\Http\Request;

class FacilityController extends Controller
{
    public function index()
    {
        $facilities = Facility::withCount('reservations')->orderBy('name')->get();

        return view('admin.facilities.index', compact('facilities'));
    }

    protected array $customAttributes = [
        'name' => 'tesis adı',
    ];

    public function store(Request $request)
    {
        $data = $request->validateWithBag('create', [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'capacity' => ['required', 'integer', 'min:1'],
        ], [], $this->customAttributes);

        Facility::create($data);

        return back()->with('success', 'Tesis eklendi.');
    }

    public function update(Request $request, Facility $facility)
    {
        $data = $request->validateWithBag('edit-'.$facility->id, [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'capacity' => ['required', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ], [], $this->customAttributes);

        $data['is_active'] = $request->boolean('is_active');
        $facility->update($data);

        return back()->with('success', 'Tesis güncellendi.');
    }
}
