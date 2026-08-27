<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Users\UserProvisioningService;
use App\Services\Email\EmailTemplateService;
use App\Services\Reports\PlatformReportingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlatformOrganizationController extends Controller
{
    public function index(Request $request): View
    {
        $this->platformOnly($request);

        $organizations = Organization::withCount('users')->orderBy('name')->paginate(12);

        return view('settings.organizations', compact('organizations'));
    }

    public function store(Request $request, UserProvisioningService $provisioning, EmailTemplateService $emails): RedirectResponse
    {
        $actor = $this->platformOnly($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:60', Rule::unique('organizations', 'slug')->whereNull('deleted_at')],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
            'admin_first_name' => ['required', 'string', 'max:50'],
            'admin_last_name' => ['required', 'string', 'max:50'],
            'admin_email' => ['required', 'email', 'max:50', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'admin_phone' => ['nullable', 'string', 'max:20'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $slug = Organization::normalizeSlug(($data['slug'] ?? '') ?: $data['name']);
        if ($slug === '' || Organization::where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['slug' => 'Choose a different farm name or slug.']);
        }
        $data['slug'] = $slug;

        [$organization, $admin] = DB::transaction(function () use ($data, $actor, $provisioning): array {
            // Older versions retired farms and users with soft deletes. Remove
            // any such legacy rows now so their unique slug/email can be used
            // by a genuinely new account.
            if ($retired = Organization::onlyTrashed()->where('slug', $data['slug'])->first()) {
                $this->permanentlyDeleteOrganization($retired);
            }
            User::onlyTrashed()->where('email', strtolower(trim($data['admin_email'])))
                ->get()->each->forceDelete();

            $organization = Organization::create([
                'name' => trim($data['name']),
                'legal_name' => $data['legal_name'] ?? null,
                'slug' => $data['slug'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'is_active' => true,
            ]);

            $admin = $provisioning->provision([
                'first_name' => $data['admin_first_name'],
                'last_name' => $data['admin_last_name'],
                'email' => $data['admin_email'],
                'phone' => $data['admin_phone'] ?? null,
                'password' => $data['admin_password'],
                'role' => 'admin',
                'organization_id' => $organization->id,
                'active_status' => true,
            ], $actor);

            return [$organization, $admin];
        });

        $resetToken = Password::broker()->createToken($admin);
        $passwordUrl = route('password.reset', [
            'token' => $resetToken,
            'email' => $admin->email,
        ]);

        $emailSent = $emails->send(
            (int) $organization->id,
            'users.organization_admin_welcome',
            $admin->email,
            [
                'recipient_name' => $admin->name,
                'organization_name' => $organization->name,
                'login_url' => route('login'),
                'login_email' => $admin->email,
                'temporary_password' => $data['admin_password'],
                'action_url' => $passwordUrl,
                'store_name' => $organization->name,
                'support_email' => $organization->email,
                'support_phone' => $organization->phone,
            ],
        );

        return redirect()->route('settings.organizations')->with(
            $emailSent ? 'success' : 'warning',
            $emailSent
                ? 'Farm and its first administrator were created. Login instructions were emailed to the administrator.'
                : 'Farm and its first administrator were created, but the welcome email was not sent. Check whether the template is active and the email server is configured.',
        );
    }

    public function show(Request $request, Organization $organization, PlatformReportingService $reports): View
    {
        $this->platformOnly($request);

        $from = CarbonImmutable::now()->startOfMonth();
        $to = CarbonImmutable::now();
        $summary = $reports->build($from, $to, (int) $organization->id);
        $organization->load([
            'users' => fn ($query) => $query->orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")->orderBy('name'),
        ])->loadCount('users');

        return view('settings.organization-show', [
            'organization' => $organization,
            'summary' => $summary,
            'farm' => $summary['rows']->first(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $this->platformOnly($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:60', Rule::unique('organizations', 'slug')->ignore($organization)],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $data['slug'] = Organization::normalizeSlug($data['slug']);
        $organization->update($data);

        return back()->with('success', 'Farm details updated successfully.');
    }

    public function destroy(Request $request, Organization $organization): RedirectResponse
    {
        $actor = $this->platformOnly($request);
        $data = $request->validate([
            'deletion_reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirmation_name' => ['required', 'string', Rule::in([$organization->name])],
        ], [
            'deletion_reason.required' => 'Explain why this organization is being removed.',
            'deletion_reason.min' => 'The deletion reason must contain at least 10 characters.',
            'confirmation_name.in' => 'Enter the organization name exactly as shown to confirm removal.',
        ]);

        DB::transaction(function () use ($organization, $actor, $data): void {
            $organization->forceFill([
                'is_active' => false,
                'deletion_reason' => trim($data['deletion_reason']),
                'deleted_by_user_id' => $actor->id,
            ])->save();

            ActivityLog::create([
                'user_id' => $actor->id,
                'organization_id' => $organization->id,
                'action' => 'organization_retired',
                'module' => 'platform_administration',
                'auditable_type' => Organization::class,
                'auditable_id' => $organization->id,
                'record_label' => $organization->name,
                'description' => 'Organization retired by platform superuser.',
                'metadata' => ['reason' => trim($data['deletion_reason'])],
            ]);

            $this->permanentlyDeleteOrganization($organization);
        });

        return redirect()->route('settings.organizations')
            ->with('success', "{$organization->name} and its user accounts were permanently deleted.");
    }

    private function platformOnly(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->isSuperuser() && $user->organization_id === null, 403);
        return $user;
    }

    private function permanentlyDeleteOrganization(Organization $organization): void
    {
        $farmUsers = $organization->users()->withTrashed()->get(['users.id', 'users.email']);

        if ($farmUsers->isNotEmpty()) {
            DB::table('sessions')->whereIn('user_id', $farmUsers->pluck('id'))->delete();
            DB::table('password_reset_tokens')->whereIn('email', $farmUsers->pluck('email')->filter())->delete();
            $farmUsers->each->forceDelete();
        }

        $organization->forceDelete();
    }
}
