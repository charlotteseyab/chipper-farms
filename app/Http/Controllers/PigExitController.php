<?php

namespace App\Http\Controllers;

use App\Http\Requests\Stock\RecordPigExitRequest;
use App\Models\PigExit;
use App\Models\PigManagement;
use App\Services\Health\Exceptions\PigUnderWithdrawalException;
use App\Services\Health\WithdrawalStatusService;
use App\Services\Stock\RecordPigExitService;
use Illuminate\Http\Request;

/** Transfer out and cull: the exits that are neither a sale nor a death. */
class PigExitController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', PigExit::class);

        $exits = PigExit::with(['pig', 'recorder'])
            ->when(in_array($request->query('type'), array_keys(PigExit::TYPES), true), fn ($q) => $q->where('exit_type', $request->query('type')))
            ->latest('exit_date')->latest('id')
            ->paginate(20)->withQueryString();

        return view('pig-exits.index', compact('exits'));
    }

    public function create(Request $request, PigManagement $pig)
    {
        $this->authorize('create', PigExit::class);
        $type = in_array($request->query('type'), array_keys(PigExit::TYPES), true) ? $request->query('type') : PigExit::TRANSFER_OUT;

        if ($pig->sales()->exists() || $pig->deaths()->exists() || $pig->herdExit()->exists()) {
            return redirect()->route('pigs.show', $pig->id)->with('error', 'This pig has already left the herd.');
        }

        return view('pig-exits.create', [
            'pig' => $pig->loadMissing('currentEarTagAssignment.tag.color'),
            'type' => $type,
            'withdrawal' => app(WithdrawalStatusService::class)->forPig($pig),
        ]);
    }

    public function store(RecordPigExitRequest $request, PigManagement $pig, RecordPigExitService $service)
    {
        try {
            $exit = $service->execute($pig, $request->user(), $request->validated());
        } catch (\DomainException|PigUnderWithdrawalException|\App\Services\Stock\StockValidationException $e) {
            return back()->withInput()->withErrors(['exit' => $e->getMessage()]);
        }

        return redirect()->route('pig-exits.index')
            ->with('success', "{$exit->type_label} recorded for pig {$pig->code}. Its tag has been released.");
    }
}
