<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Storefront\StorefrontBuyer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BuyerNotificationController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $buyer = $this->buyer($request);
        if (! $buyer) {
            return redirect()->route('storefront.buyer.login')->with('error', 'Sign in to view your notifications.');
        }

        return view('storefront.buyer.notifications', [
            'buyer' => $buyer,
            'notifications' => $buyer->notifications()->latest()->paginate(20),
        ]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $buyer = $this->buyer($request);
        abort_unless($buyer, 404);

        $notification = $buyer->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        $link = $notification->data['link'] ?? null;
        return is_string($link) && $link !== '' ? redirect()->to($link) : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $buyer = $this->buyer($request);
        abort_unless($buyer, 404);
        $buyer->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    protected function buyer(Request $request): ?StorefrontBuyer
    {
        $id = $request->session()->get('storefront_buyer_id');
        return $id ? StorefrontBuyer::find($id) : null;
    }
}
