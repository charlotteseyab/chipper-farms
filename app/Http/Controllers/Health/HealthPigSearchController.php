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
        $data = $request->validate(['q' => ['required', 'string', 'max:100'], 'departed' => ['nullable', 'boolean']]);

        return response()->json($finder->search($data['q'], (bool) ($data['departed'] ?? false)));
    }

    public function room(Room $room, HealthPigFinder $finder)
    {
        return response()->json($finder->inRoom($room->id));
    }
}
