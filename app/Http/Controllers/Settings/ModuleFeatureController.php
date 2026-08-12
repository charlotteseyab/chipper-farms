<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\OrganizationFeatureFlag;
use App\Services\Organizations\OrganizationFeatureService;
use App\Support\CurrentOrganization;
use App\Support\Features\FeatureCatalog;
use App\Support\Features\ModulePreset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Settings → Modules & Features (Phase 1, prompt §15).
 *
 * Every action here is guarded by `permission:manage_feature_flags` in the route
 * definition AND an explicit superuser check in this controller. Module flags
 * change the product surface for everyone in the organisation, so QA-001 keeps
 * the screen narrower than the seeded permission alone. The controller
 * deliberately does not consult feature flags to decide whether the user may
 * act — a flag layer that gated its own settings screen could lock a superuser
 * out of the switch that turns it back on.
 */
class ModuleFeatureController extends Controller
{
    public function __construct(
        protected OrganizationFeatureService $features,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorizeSuperuser($request);

        $organization = $this->currentOrganization->get();

        $state   = $this->features->map();
        $current = $this->features->currentPreset();

        $rows = OrganizationFeatureFlag::query()
            ->when($organization, fn ($q) => $q->forOrganization($organization->id))
            ->with(['enabledBy:id,name', 'disabledBy:id,name'])
            ->get()
            ->keyBy('feature_key');

        // Group for display: domain first, essentials before advanced, so the
        // screen reads as "here is your everyday farm, here is the optional
        // accounting depth" rather than as an alphabetical wall of switches.
        $grouped = [];
        foreach (FeatureCatalog::all() as $key => $definition) {
            $grouped[$definition['domain']][$definition['category']][$key] = [
                'definition' => $definition,
                'enabled'    => $state[$key],
                'usable'     => $this->features->usable($key),
                'row'        => $rows->get($key),
                'dependents' => FeatureCatalog::dependentsDeep($key),
                'requires'   => FeatureCatalog::requiresDeep($key),
            ];
        }

        return view('settings.modules', [
            'organization'  => $organization,
            'grouped'       => $grouped,
            'presets'       => ModulePreset::all(),
            'currentPreset' => $current['preset'],
            'presetDrift'   => $current['drift'],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeSuperuser($request);

        $validated = $request->validate([
            'feature_key' => ['required', 'string', Rule::in(FeatureCatalog::keys())],
            'enabled'     => ['required', 'boolean'],
        ]);

        $key    = $validated['feature_key'];
        $actor  = $request->user();
        $enable = (bool) $validated['enabled'];

        if ($enable) {
            $touched = $this->features->enable($key, actor: $actor);
            $extra   = array_values(array_diff($touched, [$key]));

            $message = FeatureCatalog::definition($key)['label'] . ' is now on.';
            if ($extra !== []) {
                $message .= ' We also switched on ' . $this->labelList($extra)
                    . ', which it needs to work.';
            }
        } else {
            $touched = $this->features->disable($key, actor: $actor);
            $extra   = array_values(array_diff($touched, [$key]));

            $message = FeatureCatalog::definition($key)['label'] . ' is now off.';
            if ($extra !== []) {
                $message .= ' We also switched off ' . $this->labelList($extra)
                    . ', which depends on it.';
            }
        }

        return redirect()
            ->route('settings.modules')
            ->with('success', $message);
    }

    public function applyPreset(Request $request): RedirectResponse
    {
        $this->authorizeSuperuser($request);

        $validated = $request->validate([
            'preset'    => ['required', 'string', Rule::in(ModulePreset::codes())],
            'exclusive' => ['sometimes', 'boolean'],
        ]);

        $code      = $validated['preset'];
        $exclusive = (bool) ($validated['exclusive'] ?? false);

        $result = $this->features->applyPreset(
            $code,
            actor: $request->user(),
            exclusive: $exclusive,
        );

        $label   = ModulePreset::definition($code)['label'];
        $message = "Applied the {$label} setup.";

        if ($result['disabled'] !== []) {
            $message .= ' ' . count($result['disabled']) . ' other module'
                . (count($result['disabled']) === 1 ? ' was' : 's were')
                . ' switched off.';
        }

        return redirect()
            ->route('settings.modules')
            ->with('success', $message);
    }

    /** @param list<string> $keys */
    protected function labelList(array $keys): string
    {
        $labels = array_map(
            static fn (string $k): string => FeatureCatalog::definition($k)['label'],
            $keys,
        );

        if (count($labels) === 1) {
            return $labels[0];
        }

        $last = array_pop($labels);

        return implode(', ', $labels) . ' and ' . $last;
    }

    protected function authorizeSuperuser(Request $request): void
    {
        abort_unless($request->user()?->isSuperuser(), 403);
    }
}
