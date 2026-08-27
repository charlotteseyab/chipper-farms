<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Storefront\StorefrontBuyer;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontQuote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Services\Email\EmailTemplateService;
use App\Storefront\Support\StorefrontSettings;
use App\Storefront\Services\QuoteService;
use App\Storefront\Services\Exceptions\InvalidQuoteTransitionException;
use App\Storefront\Services\Exceptions\PaymentException;
use App\Storefront\Services\PaystackService;
use App\Storefront\Support\StorefrontCatalog;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BuyerAuthController extends Controller
{
    public function showLogin(Request $request): View
    {
        return view('storefront.buyer.login', [
            'claimReference' => $request->query('claim_reference'),
            'claimToken' => $request->query('claim_token'),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email', 'max:190'],
            'password' => ['required', 'string'],
            'claim_reference' => ['nullable', 'string', 'required_with:claim_token'],
            'claim_token' => ['nullable', 'string', 'required_with:claim_reference'],
        ]);

        $buyer = StorefrontBuyer::query()
            ->where('email', strtolower($data['email']))
            ->when(app(\App\Support\CurrentOrganization::class)->id(), fn ($q, $orgId) => $q->where('organization_id', $orgId))
            ->first();

        if (! $buyer || ! Hash::check($data['password'], $buyer->password)) {
            throw ValidationException::withMessages([
                'email' => 'These buyer account details do not match our records.',
            ]);
        }

        $buyer->forceFill(['last_login_at' => now()])->save();

        $claimedOrder = null;
        if (! empty($data['claim_reference']) && ! empty($data['claim_token'])) {
            $claimedOrder = $this->locateGuestOrder($data['claim_reference'], $data['claim_token']);
            if (strtolower(trim((string) $claimedOrder->guest_email)) !== $buyer->email) {
                throw ValidationException::withMessages([
                    'email' => 'Sign in with the same email address used for this order.',
                ]);
            }
            $this->claimMatchingOrders($buyer, $claimedOrder);
        }

        $request->session()->put('storefront_buyer_id', $buyer->getKey());
        $request->session()->regenerate();

        return $claimedOrder
            ? redirect()->route('storefront.buyer.orders.show', $claimedOrder)->with('success', 'Signed in and saved your guest orders to this account.')
            : redirect()->route('storefront.buyer.orders.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('storefront_buyer_id');

        return redirect()->route('storefront.home')->with('success', 'You have signed out of your buyer account.');
    }

    public function createFromOrder(Request $request, string $reference, string $token): View
    {
        $order = $this->locateGuestOrder($reference, $token);

        return view('storefront.buyer.register-from-order', [
            'order' => $order,
            'token' => $token,
        ]);
    }

    public function storeFromOrder(Request $request, string $reference, string $token): RedirectResponse
    {
        $order = $this->locateGuestOrder($reference, $token);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:200'],
            'email'    => ['required', 'email', 'max:190'],
            'phone'    => ['nullable', 'string', 'max:60'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = strtolower(trim($data['email']));

        if (filled($order->guest_email) && $email !== strtolower(trim($order->guest_email))) {
            throw ValidationException::withMessages([
                'email' => 'Use the email address that received this order link.',
            ]);
        }

        if (StorefrontBuyer::query()
            ->where('organization_id', $order->organization_id)
            ->where('email', $email)
            ->exists()) {
            throw ValidationException::withMessages([
                'email' => 'A buyer account already exists for this email. Sign in to continue.',
            ]);
        }

        $buyer = DB::transaction(function () use ($order, $data, $email): StorefrontBuyer {
            $buyer = StorefrontBuyer::create([
                'organization_id' => $order->organization_id,
                'counterparty_id' => $order->counterparty_id,
                'name'            => trim($data['name']),
                'email'           => $email,
                'phone'           => $data['phone'] ?? $order->guest_phone,
                'password'        => Hash::make($data['password']),
                'last_login_at'   => now(),
            ]);

            $this->claimMatchingOrders($buyer, $order);

            return $buyer;
        });

        $request->session()->put('storefront_buyer_id', $buyer->getKey());
        $request->session()->regenerate();

        app(EmailTemplateService::class)->send(
            (int) $order->organization_id,
            'storefront.buyer_welcome',
            $buyer->email,
            [
                'customer_name' => $buyer->name,
                'order_reference' => $order->reference,
                'store_name' => StorefrontSettings::storeName($order->organization_id),
                'support_email' => StorefrontSettings::supportEmail($order->organization_id),
                'support_phone' => StorefrontSettings::supportPhone($order->organization_id),
                'action_url' => route('storefront.buyer.orders.index'),
            ],
        );

        app(\App\Services\Notifications\InAppNotificationService::class)->notifyBuyer(
            $buyer,
            'storefront.buyer_welcome',
            'Buyer account ready',
            "Your account is ready and order {$order->reference} has been saved.",
            route('storefront.buyer.orders.show', $order),
            context: ['order_id' => $order->id],
        );

        return redirect()
            ->route('storefront.buyer.orders.show', $order)
            ->with('success', 'Your buyer account is ready and this order is saved to it.');
    }

    public function orders(Request $request): View|RedirectResponse
    {
        $buyer = $this->buyer($request);
        if (! $buyer) {
            return redirect()->route('storefront.buyer.login')->with('error', 'Sign in to your buyer account first.');
        }

        $orders = StorefrontOrder::query()
            ->where('storefront_buyer_id', $buyer->getKey())
            ->withCount('items')
            ->latest('placed_at')
            ->latest('id')
            ->get();

        return view('storefront.buyer.orders.index', [
            'buyer' => $buyer,
            'orders' => $orders,
        ]);
    }

    public function order(Request $request, StorefrontOrder $order): View|RedirectResponse
    {
        $buyer = $this->buyer($request);
        if (! $buyer) {
            return redirect()->route('storefront.buyer.login')->with('error', 'Sign in to your buyer account first.');
        }

        abort_unless((int) $order->storefront_buyer_id === (int) $buyer->getKey(), 404);

        $order->load(['items', 'events']);

        return view('storefront.buyer.orders.show', [
            'buyer' => $buyer,
            'order' => $order,
        ]);
    }

    public function retryOrderPayment(Request $request, StorefrontOrder $order, PaystackService $paystack): RedirectResponse
    {
        $buyer = $this->buyer($request);
        if (! $buyer) {
            return redirect()->route('storefront.buyer.login')->with('error', 'Sign in to your buyer account first.');
        }

        abort_unless((int) $order->storefront_buyer_id === (int) $buyer->getKey(), 404);

        if ($order->payment_method !== StorefrontOrder::METHOD_PAYSTACK
            || $order->payment_status !== StorefrontOrder::PAY_PAYSTACK_PEND
            || $order->status !== StorefrontOrder::STATUS_PENDING_PAYMENT) {
            return back()->with('error', 'This order is not awaiting an online payment.');
        }

        try {
            $result = $paystack->initialise($order, route('storefront.account.paystack.callback'));
        } catch (PaymentException $exception) {
            return back()->with('error', 'Could not start payment. '.$exception->customerMessage());
        }

        return redirect()->away($result['authorization_url']);
    }

    public function quotes(Request $request): View|RedirectResponse
    {
        $buyer = $this->buyer($request);
        if (! $buyer) {
            return redirect()->route('storefront.buyer.login')->with('error', 'Sign in to your buyer account first.');
        }

        $quotes = StorefrontQuote::query()
            ->where('organization_id', $buyer->organization_id)
            ->whereRaw('LOWER(contact_email) = ?', [strtolower($buyer->email)])
            ->withCount('items')
            ->latest()
            ->get();

        return view('storefront.buyer.quotes.index', compact('buyer', 'quotes'));
    }

    public function quote(Request $request, StorefrontQuote $quote): View|RedirectResponse
    {
        $buyer = $this->buyer($request);
        if (! $buyer) {
            return redirect()->route('storefront.buyer.login')->with('error', 'Sign in to your buyer account first.');
        }

        abort_unless(
            (int) $quote->organization_id === (int) $buyer->organization_id
            && strtolower(trim((string) $quote->contact_email)) === strtolower($buyer->email),
            404,
        );

        $quote->load('items');

        $paymentMethods = StorefrontCatalog::enabledPaymentMethods((int) $quote->organization_id);

        return view('storefront.buyer.quotes.show', compact('buyer', 'quote', 'paymentMethods'));
    }

    public function acceptQuote(Request $request, StorefrontQuote $quote, QuoteService $quotes, PaystackService $paystack): RedirectResponse
    {
        $buyer = $this->buyer($request);
        if (! $buyer) {
            return redirect()->route('storefront.buyer.login')->with('error', 'Sign in to your buyer account first.');
        }

        $this->assertQuoteBelongsToBuyer($quote, $buyer);

        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'in:cod,paystack'],
        ]);

        try {
            $order = $quotes->acceptAndConvertByBuyer($quote, $buyer, $validated['payment_method']);
        } catch (InvalidQuoteTransitionException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if ($order->payment_method === StorefrontOrder::METHOD_PAYSTACK
            && $order->payment_status === StorefrontOrder::PAY_PAYSTACK_PEND) {
            try {
                $result = $paystack->initialise($order, route('storefront.account.paystack.callback'));
            } catch (PaymentException $exception) {
                return redirect()->route('storefront.buyer.orders.show', $order)
                    ->with('error', 'Your order was created, but payment could not be started. You can safely try again. '.$exception->customerMessage());
            }

            return redirect()->away($result['authorization_url']);
        }

        return redirect()->route('storefront.buyer.orders.show', $order)
            ->with('success', "Quote {$quote->reference} accepted. Order {$order->reference} has been created.");
    }

    public function declineQuote(Request $request, StorefrontQuote $quote, QuoteService $quotes): RedirectResponse
    {
        $buyer = $this->buyer($request);
        if (! $buyer) {
            return redirect()->route('storefront.buyer.login')->with('error', 'Sign in to your buyer account first.');
        }

        $this->assertQuoteBelongsToBuyer($quote, $buyer);

        try {
            $quotes->declineByBuyer($quote, $buyer);
        } catch (InvalidQuoteTransitionException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('storefront.buyer.quotes.show', $quote)
            ->with('success', "Quote {$quote->reference} declined.");
    }

    protected function assertQuoteBelongsToBuyer(StorefrontQuote $quote, StorefrontBuyer $buyer): void
    {
        abort_unless(
            (int) $quote->organization_id === (int) $buyer->organization_id
            && strtolower(trim((string) $quote->contact_email)) === strtolower($buyer->email),
            404,
        );
    }

    protected function buyer(Request $request): ?StorefrontBuyer
    {
        $id = $request->session()->get('storefront_buyer_id');
        $buyer = $id ? StorefrontBuyer::find($id) : null;

        return $buyer;
    }

    protected function locateGuestOrder(string $reference, string $token): StorefrontOrder
    {
        $orders = StorefrontOrder::query()
            ->where('reference', $reference)
            ->whereNotNull('guest_claim_token')
            ->get();

        foreach ($orders as $order) {
            if (is_string($order->guest_claim_token) && hash_equals($order->guest_claim_token, $token)) {
                return $order;
            }
        }

        abort(404);
    }

    protected function claimMatchingOrders(StorefrontBuyer $buyer, StorefrontOrder $proofOrder): void
    {
        $email = strtolower(trim((string) $proofOrder->guest_email));

        StorefrontOrder::query()
            ->where('organization_id', $proofOrder->organization_id)
            ->whereNull('storefront_buyer_id')
            ->whereNotNull('guest_claim_token')
            ->whereRaw('LOWER(guest_email) = ?', [$email])
            ->update([
                'storefront_buyer_id' => $buyer->getKey(),
                'guest_claim_token' => null,
                'claimed_at' => now(),
            ]);

        $proofOrder->refresh();
    }
}
