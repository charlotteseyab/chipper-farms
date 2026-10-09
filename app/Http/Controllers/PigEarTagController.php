<?php

namespace App\Http\Controllers;

use App\Models\EarTag;
use App\Models\EarTagColor;
use App\Models\PigManagement;
use App\Services\Stock\StockValidationException;
use App\Services\Stock\Tags\EarTagAllocator;
use App\Services\Stock\Tags\ReplaceEarTagService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PigEarTagController extends Controller
{
    use AuthorizesRequests;

    public function edit(PigManagement $pig, EarTagAllocator $allocator)
    {
        $this->authorize('update', $pig);
        $pig->load('currentEarTagAssignment.tag.color');
        $colors = EarTagColor::where('is_active', true)->orderBy('name')->get();
        $suggestions = $colors->mapWithKeys(fn ($color) => [
            $color->id => $allocator->propose((int) $pig->organization_id, $color, 5),
        ]);

        return view('pigs.tags.replace', compact('pig', 'colors', 'suggestions'));
    }

    public function update(Request $request, PigManagement $pig, ReplaceEarTagService $service)
    {
        $this->authorize('update', $pig);
        $data = $request->validate([
            'old_tag_status' => ['required', 'in:available,lost,damaged,retired'],
            'ear_tag_color_id' => [
                'required',
                'integer',
                Rule::exists('ear_tag_colors', 'id')->where(
                    fn ($query) => $query
                        ->where('organization_id', $pig->organization_id)
                        ->where('is_active', true)
                ),
            ],
            'ear_tag_number' => ['required', 'string', 'max:50'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        try {
            $service->execute($pig, $request->user(), $data);
        } catch (StockValidationException $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()])->withInput();
        }

        return redirect()->route('pigs.show', $pig)->with('success', 'The physical tag was replaced and both assignments were preserved.');
    }
}
