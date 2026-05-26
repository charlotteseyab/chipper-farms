<?php

namespace App\Http\Controllers;

use App\Models\Structure;
use App\Models\Room;
use Illuminate\Http\Request;

class StructureController extends Controller
{
    public function index()
    {
        $this->abortUnlessRole('superuser', 'admin', 'farmmanager');
        $structures = Structure::withCount('rooms')->orderBy('name')->paginate(15);
        return view('structures.index', compact('structures'));
    }

    public function create()
    {
        $this->abortUnlessRole('superuser', 'admin', 'farmmanager');
        return view('structures.create');
    }

    public function store(Request $request)
    {
        $this->abortUnlessRole('superuser', 'admin', 'farmmanager');
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'capacity' => 'nullable|integer|min:0',
            'status' => 'required|in:active,inactive,under_maintenance'
        ]);



        $data['created_by'] = auth()->id();
        $structure = Structure::create($data);

        return redirect()->route('structures.show', $structure)->with('success', 'Structure created');
    }

    public function show(Structure $structure)
    {
        $this->abortUnlessRole('superuser', 'admin', 'farmmanager');
        $structure->load(['rooms' => function ($q) { $q->orderBy('created_at', 'asc'); }]);
        return view('structures.show', compact('structure'));
    }

    public function edit(Structure $structure)
    {
        $this->abortUnlessRole('superuser', 'admin', 'farmmanager');
        return view('structures.edit', compact('structure'));
    }

    public function update(Request $request, Structure $structure)
    {
        $this->abortUnlessRole('superuser', 'admin', 'farmmanager');
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'capacity' => 'nullable|integer|min:0',
            'status' => 'required|in:active,inactive,under_maintenance'
        ]);

        $structure->update($data);
        return redirect()->route('structures.show', $structure)->with('success', 'Structure updated');
    }

    public function destroy(Structure $structure)
    {
        $this->abortUnlessRole('superuser', 'admin', 'farmmanager');
        $structure->delete();
        return redirect()->route('structures.index')->with('success', 'Structure deleted');
    }
}

