<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\RecordFeedSaleRequest;
use App\Models\FeedSale;
use App\Models\FeedType;
use App\Services\Sales\Exceptions\FeedSaleException;
use App\Services\Sales\RecordFeedSaleService;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Feed-sale management (direct/internal channel). The storefront module will
 * later call RecordFeedSaleService directly with channel=storefront.
 *
 *   index   list feed sales (view_feed_sales)
 *   create  form (record_feed_sales)
 *   store   record a sale → dispatches FeedSaleRecorded (record_feed_sales)
 */
class FeedSaleController extends Controller
{
    public function __construct(
        protected CurrentOrganization $currentOrg,
        protected RecordFeedSaleService $recorder,
    ) {
    }

    public function index()
    {
        $orgId = $this->currentOrg->id();
        $sales = FeedSale::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->with('feedType')
            ->orderByDesc('id')
            ->paginate(25);

        return view('finance.feed-sales.index', ['sales' => $sales]);
    }

    public function create()
    {
        return view('finance.feed-sales.create', [
            'feedTypes' => FeedType::query()->orderBy('name')->get(),
        ]);
    }

    public function store(RecordFeedSaleRequest $request)
    {
        try {
            $feedType = FeedType::findOrFail($request->validated()['feed_type_id']);
            $sale = $this->recorder->execute($feedType, $request->user(), $request->validated());

            return redirect()->route('finance.feed-sales.index')
                ->with('success', "Feed sale recorded (#{$sale->id}). Revenue and COGS will post via the finance pipeline.");
        } catch (FeedSaleException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (\Throwable $e) {
            Log::error('FeedSaleController::store failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'Could not record feed sale: ' . $e->getMessage())->withInput();
        }
    }
}
