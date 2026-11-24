<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationFeatureFlag;
use App\Services\Organizations\OrganizationFeatureService;
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
 *
 * WHICH FARM
 * ----------
 * The superuser works above all farms and has no farm of their own, so every
 * view and every change names its target: one farm (`farm=<id>`), or every
 * active farm (`farm=all`), which must be chosen deliberately. Advanced
 * features are sold per farm; a switch that silently applied to every farm
 * gave the add-on away to all of them.
 *
 * A setup (preset) makes the farm's finance match it: finance features the
 * setup does not include are switched off. Overlay setups (the shop) only add.
  */
class ModuleFeatureController extends Controller
{
    public function __construct(
        protected OrganizationFeatureService $features,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorizeSuperuser($request);

        $farms = Organization::active()->orderBy('name')->get(['id', 'name']);
        $target = (string) $request->query('farm', '');
        $organization = ctype_digit($target) ? $farms->firstWhere('id', (int) $target) : null;
        $allFarms = $target === 'all';

        $view = [
            'farms'         => $farms,
            'organization'  => $organization,
            'allFarms'      => $allFarms,
            'farm'          => $organization?->id ?? ($allFarms ? 'all' : null),
            'grouped'       => [],
            'presets'       => ModulePreset::all(),
            'currentPreset' => null,
            'presetDrift'   => [],
        ];

        // Nothing is shown to switch until a farm (or all farms) is chosen.
        if (! $organization && ! $allFarms) {
            return view('settings.modules', $view);
        }

        if ($organization) {
            $state = $this->features->map($organization);
            $current = $this->features->currentPreset($organization);
            $view['currentPreset'] = $current['preset'];
            $view['presetDrift'] = $current['drift'];
            $rows = OrganizationFeatureFlag::query()
                ->forOrganization($organization->id)
                ->with(['enabledBy:id,name', 'disabledBy:id,name'])
                ->get()
                ->keyBy('feature_key');
        } else {
            // For all farms: how many farms have each feature on.
            $onCount = array_fill_keys(FeatureCatalog::keys(), 0);
            foreach ($farms as $farm) {
                foreach ($this->features->map($farm) as $key => $on) {
                    $onCount[$key] += $on ? 1 : 0;
                }
            }
        }

        foreach (FeatureCatalog::all() as $key => $definition) {
            $view['grouped'][$definition['domain']][$definition['category']][$key] = [
                'definition' => $definition,
                'enabled'    => $organization ? $state[$key] : null,
                'usable'     => $organization ? $this->features->usable($key, $organization) : null,
                'on_count'   => $organization ? null : $onCount[$key],
                'row'        => $organization ? $rows->get($key) : null,
                'dependents' => FeatureCatalog::dependentsDeep($key),
                'requires'   => FeatureCatalog::requiresDeep($key),
            ];
        }

        return view('settings.modules', $view);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeSuperuser($request);

        $validated = $request->validate([
            'farm'        => ['required', 'string'],
            'feature_key' => ['required', 'string', Rule::in(FeatureCatalog::keys())],
            'enabled'     => ['required', 'boolean'],
        ]);

        $key    = $validated['feature_key'];
        $actor  = $request->user();
        $enable = (bool) $validated['enabled'];
        $farms  = $this->targetFarms($validated['farm']);
        $label  = FeatureCatalog::definition($key)['label'];

        $touched = [];
        foreach ($farms as $farm) {
            $touched = $enable
                ? $this->features->enable($key, $farm, actor: $actor)
                : $this->features->disable($key, $farm, actor: $actor);
        }

        $where = $validated['farm'] === 'all'
            ? 'for all ' . $farms->count() . ' active farms'
            : 'for ' . $farms->first()->name;
        $message = "{$label} is now " . ($enable ? 'on' : 'off') . " {$where}.";
        $extra = array_values(array_diff($touched, [$key]));
        if ($extra !== []) {
            $message .= $enable
                ? ' We also switched on ' . $this->labelList($extra) . ', which it needs to work.'
                : ' We also switched off ' . $this->labelList($extra) . ', which depends on it.';
        }

        return redirect()
            ->route('settings.modules', ['farm' => $validated['farm']])
            ->with('success', $message);
    }

    public function applyPreset(Request $request): RedirectResponse
    {
        $this->authorizeSuperuser($request);

        $validated = $request->validate([
            'farm'   => ['required', 'string'],
            'preset' => ['required', 'string', Rule::in(ModulePreset::codes())],
        ]);

        $code  = $validated['preset'];
        $farms = $this->targetFarms($validated['farm']);

        // A finance setup is a level: the farm gets exactly its finance
        // features. The shop is left alone; overlays only add.
        $exclusive = ! ModulePreset::isOverlay($code);
        $disabled = [];
        foreach ($farms as $farm) {
            $result = $this->features->applyPreset(
                $code, $farm, actor: $request->user(), exclusive: $exclusive,
                resetOnly: FeatureCatalog::inDomain(FeatureCatalog::DOMAIN_FINANCE),
            );
            $disabled = $result['disabled'];
        }

        $label = ModulePreset::definition($code)['label'];
        $message = $validated['farm'] === 'all'
            ? "Applied the {$label} setup to all {$farms->count()} active farms."
            : "Applied the {$label} setup to {$farms->first()->name}.";
        if ($validated['farm'] !== 'all' && $disabled !== []) {
            $message .= ' Switched off: ' . $this->labelList($disabled) . '.';
        }

        return redirect()
            ->route('settings.modules', ['farm' => $validated['farm']])
            ->with('success', $message);
    }

    /**
     * The farms a change applies to: one active farm by id, or every active
     * farm for the literal `all`. Anything else is refused.
     *
     * @return \Illuminate\Support\Collection<int, Organization>
     */
    protected function targetFarms(string $farm): \Illuminate\Support\Collection
    {
        if ($farm === 'all') {
            return Organization::active()->get();
        }

        $organization = ctype_digit($farm) ? Organization::active()->find((int) $farm) : null;
        if ($organization === null) {
            throw \Illuminate\Validation\ValidationException::withMessages(['farm' => 'Choose a farm.']);
        }

        return collect([$organization]);
    }

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
