<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\Health\HealthPigFinder;
use Illuminate\Http\Request;

/** Search used by the pig picker on health forms. */
class HealthPigSearchController extends Controller
{
    public function search(Request $request, HealthPigFinder $finder)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'departed' => ['nullable', 'boolean']]);

        // Nothing typed: the list the picker shows when opened.
        if (trim((string) ($data['q'] ?? '')) === '') {
            return response()->json($finder->browse());
        }

        return response()->json($finder->search($data['q'], (bool) ($data['departed'] ?? false)));
    }

    public function room(Room $room, HealthPigFinder $finder)
    {
        return response()->json($finder->inRoom($room->id));
    }
}
