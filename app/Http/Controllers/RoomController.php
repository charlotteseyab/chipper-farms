<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Structure;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function storeGlobal(Request $request)
    {
        $data = $request->validate([
            'structure_id' => 'required|exists:structures,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'capacity' => 'nullable|integer|min:0',
            'type' => 'required|in:maternity,gestation,weaning,finisher,quarantine,general',
            'notes' => 'nullable|string'
        ]);

        // Check if structure is full
        $structure = Structure::find($data['structure_id']);
        if ($structure->is_full) {
            return back()->withErrors(['structure' => 'This structure is at full capacity. Cannot add more rooms.'])->withInput();
        }

        Room::create($data);

        return redirect()->route('structures.show', $data['structure_id'])->with('success', 'Room added');
    }

    public function store(Request $request, Structure $structure)
    {
        // Check if structure is full
        if ($structure->is_full) {
            return back()->withErrors(['structure' => 'This structure is at full capacity. Cannot add more rooms.'])->withInput();
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'capacity' => 'nullable|integer|min:0',
            'type' => 'required|in:maternity,gestation,weaning,finisher,quarantine,general',
            'notes' => 'nullable|string'
        ]);

        $data['structure_id'] = $structure->id;
        Room::create($data);

        return redirect()->route('structures.show', $structure)->with('success', 'Room added');
    }

    public function destroy(Structure $structure, Room $room)
    {
        if ($room->structure_id !== $structure->id) {
            abort(404);
        }
        $room->delete();
        return redirect()->route('structures.show', $structure)->with('success', 'Room deleted');
    }

    public function listByStructure(Structure $structure)
    {
        return response()->json(
            $structure->rooms()->orderBy('created_at', 'asc')->get(['id','name','code','type','capacity'])
        );
    }

    public function show(Room $room)
    {
        $room->load(['structure', 'pigs' => function($query) {
            $query->orderBy('ear_tag_number');
        }]);
        
        return view('rooms.show', compact('room'));
    }
}


