<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Structure;
use App\Models\PigManagement;
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

    // --- NEW API Methods for Cascading Dropdowns ---

    /**
     * API: Fetches rooms (ID and Name) associated with a given structure ID.
     * Used by the front-end to populate the Room dropdown dynamically.
     */
    public function apiListRoomsByStructure($structureId)
    {
        $rooms = Room::where('structure_id', $structureId)
                     // Select only the fields needed by the frontend: 'id' and 'name'
                     ->get(['id', 'name']);

        return response()->json($rooms);
    }

    /**
     * API: Fetches pigs (ID and Ear Tag Number) associated with a given room ID.
     * This method resolves the "No Items Found" issue for the Pig dropdown.
     */
    public function apiListPigsByRoom($roomId)
    {
        $pigs = PigManagement::where('room_id', $roomId)
                    ->orderBy('ear_tag_number')
                    ->get(['id', 'ear_tag_number']);

        return response()->json($pigs);
    }
}
