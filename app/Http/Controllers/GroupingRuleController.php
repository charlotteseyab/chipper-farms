<?php
namespace App\Http\Controllers;

use App\Models\GroupingRule;
use App\Models\FeedingStandard;
use Illuminate\Http\Request;

class GroupingRuleController extends Controller
{
    public function index()
    {
        $rules = GroupingRule::with('feedingStandard')->orderBy('created_at','desc')->paginate(20);
        return view('grouping_rules.index', compact('rules'));
    }

    public function create()
    {
        $standards = FeedingStandard::orderBy('pig_category')->get();
        return view('grouping_rules.create', compact('standards'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'conditions_text' => 'required|string',
            'feeding_standard_id' => 'nullable|exists:feeding_standards,id',
            'active' => 'sometimes|boolean',
        ]);

        $payload = [
            'name' => $data['name'],
            'conditions_text' => $data['conditions_text'],
            'feeding_standard_id' => $data['feeding_standard_id'] ?? null,
            'active' => $request->boolean('active'),
        ];

        GroupingRule::create($payload);
        return redirect()->route('grouping-rules.index')->with('success','Grouping rule created.');
    }

    public function edit(GroupingRule $groupingRule)
    {
        $standards = FeedingStandard::orderBy('pig_category')->get();
        return view('grouping_rules.edit', ['rule' => $groupingRule, 'standards' => $standards]);
    }

    public function update(Request $request, GroupingRule $groupingRule)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'conditions_text' => 'required|string',
            'feeding_standard_id' => 'nullable|exists:feeding_standards,id',
            'active' => 'sometimes|boolean',
        ]);

        $payload = [
            'name' => $data['name'],
            'conditions_text' => $data['conditions_text'],
            'feeding_standard_id' => $data['feeding_standard_id'] ?? null,
            'active' => $request->boolean('active'),
        ];

        $groupingRule->update($payload);
        return redirect()->route('grouping-rules.index')->with('success','Grouping rule updated.');
    }

    public function destroy(GroupingRule $groupingRule)
    {
        $groupingRule->delete();
        return back()->with('success','Grouping rule deleted.');
    }
}
