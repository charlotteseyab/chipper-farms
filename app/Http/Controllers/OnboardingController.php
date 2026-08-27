<?php

namespace App\Http\Controllers;

use App\Helpers\NavigationHelper;
use App\Models\ActivityLog;
use App\Models\EmailSetting;
use App\Models\Finance\BankAccount;
use App\Models\Finance\TaxProfile;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperuser() && ! $user->isCustomer(), 403);

        if (! $user->onboarding_required || $user->onboarding_completed_at !== null) {
            return redirect()->to(NavigationHelper::dashboardRoute($user));
        }

        $organization = $user->organization;
        $organizationId = (int) $organization->id;
        $email = EmailSetting::getSettings();
        $livePrice = (float) Setting::get('sales.pricing.live_weight_price', 0);
        $carcassPrice = (float) Setting::get('sales.pricing.carcass_weight_price', 0);

        $checklist = [
            ['Farm profile', filled($organization->email) && filled($organization->phone), 'Add the farm contact email and phone used on documents and customer communication.', 'settings.general'],
            ['Animal breeds', count(Setting::animalBreeds()) > 0, 'Review the breed catalogue and keep only breeds used on this farm.', 'settings.setup'],
            ['Sales pricing', $livePrice > 0 && $carcassPrice > 0, 'Set live-animal and carcass prices before publishing storefront listings.', 'settings.setup'],
            ['Email delivery', filled($email->from_address) && ($email->mailer !== 'smtp' || filled($email->host)), 'Email delivery is managed by the platform. Review which farm email templates should be active.', 'settings.email'],
            ['Tax profile', TaxProfile::query()->forOrganization($organizationId)->exists(), 'Confirm whether sales and purchases are taxable and configure an effective tax profile.', 'finance.tax.index'],
            ['Money accounts', BankAccount::query()->forOrganization($organizationId)->where('is_active', true)->exists(), 'Create the cash or bank accounts used for receipts, payments and reconciliation.', 'finance.bank-accounts.index'],
        ];

        return view('onboarding.show', compact('user', 'organization', 'checklist', 'livePrice', 'carcassPrice'));
    }

    public function complete(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperuser() && ! $user->isCustomer(), 403);

        $rules = [];
        if ($user->isAdmin()) {
            $rules = [
                'organization_name' => ['required', 'string', 'max:150'],
                'organization_email' => ['nullable', 'email', 'max:150'],
                'organization_phone' => ['nullable', 'string', 'max:30'],
                'organization_address' => ['nullable', 'string', 'max:1000'],
                'date_format' => ['required', 'string', 'in:d/m/Y,m/d/Y,Y-m-d'],
                'time_format' => ['required', 'string', 'in:12,24'],
            ];
        }
        $validated = $request->validate($rules);

        DB::transaction(function () use ($user, $validated): void {
            if ($user->isAdmin()) {
                $user->organization->update([
                    'name' => trim($validated['organization_name']),
                    'email' => $validated['organization_email'] ?? null,
                    'phone' => $validated['organization_phone'] ?? null,
                    'address' => $validated['organization_address'] ?? null,
                ]);
                Setting::set('date_format', $validated['date_format']);
                Setting::set('time_format', $validated['time_format']);
            }

            $user->forceFill([
                'onboarding_required' => false,
                'onboarding_completed_at' => now(),
            ])->save();

            ActivityLog::create([
                'user_id' => $user->id,
                'organization_id' => $user->organization_id,
                'action' => 'onboarding_completed',
                'module' => 'users',
                'description' => 'User completed the one-time portal onboarding.',
            ]);
        });

        return redirect()->to(NavigationHelper::dashboardRoute($user))
            ->with('success', 'Welcome to your portal. Your onboarding preferences have been saved.');
    }
}
