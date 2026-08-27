<?php

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PigletDeath;
use App\Models\PigManagement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AuditTrailController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAccess($request);

        $canViewArchives = $request->user()->isSuperuser()
            || $request->user()->isAdmin()
            || $request->user()->isManager();
        $activeTab = $canViewArchives && $request->query('tab') === 'archived' ? 'archived' : 'activity';
        $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);

        return view('audit-trail.index', [
            'activeTab' => $activeTab,
            'canViewArchives' => $canViewArchives,
            'logs' => $this->auditLogs($request),
            'summary' => $this->summary($request),
            'modules' => $this->modules(),
            'actions' => $this->actions(),
            'users' => $this->users($request),
            'pigs' => $this->archivedPigs($request, $weaningAge),
            'pigletDeaths' => $this->archivedPigletDeaths(),
            'category' => (string) $request->query('category'),
            'weaningAge' => $weaningAge,
        ]);
    }

    protected function authorizeAccess(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user && ($user->isSuperuser() || ($user->organization_id && ! $user->hasRoleName('customer'))),
            403,
        );
    }

    protected function auditLogs(Request $request)
    {
        $query = ActivityLog::with('user')->latest();
        $user = $request->user();

        $this->scopeActivityForViewer($query, $user);

        if ($request->filled('q')) {
            $search = trim((string) $request->query('q'));
            $query->where(function ($q) use ($search) {
                $q->where('record_label', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('module')) {
            $query->where('module', $request->query('module'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        if ($request->filled('user_id') && ($user->isSuperuser() || $user->isAdmin())) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        return $query->paginate(15, ['*'], 'audit_page')->withQueryString();
    }

    protected function summary(Request $request): array
    {
        $base = ActivityLog::query();
        $user = $request->user();

        $this->scopeActivityForViewer($base, $user);

        return [
            'today' => (clone $base)->whereDate('created_at', today())->count(),
            'stock' => (clone $base)->where('module', 'stock')->count(),
            'approvals' => (clone $base)->whereIn('action', ['approved', 'added_to_stock'])->count(),
            'archived_restored' => (clone $base)->whereIn('action', ['archived', 'restored'])->count(),
        ];
    }

    protected function archivedPigs(Request $request, int $weaningAge)
    {
        $query = PigManagement::onlyTrashed()
            ->with(['deaths', 'sales'])
            ->orderByDesc('deleted_at');

        $user = $request->user();
        if (! $user->isSuperuser() && $user->organization_id) {
            $query->where('organization_id', $user->organization_id);
        }

        $search = (string) $request->string('archive_search');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('ear_tag_number', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $category = (string) $request->query('category');
        if ($category === 'piglet') {
            $query->whereNotNull('date_of_birth')
                ->whereDate('date_of_birth', '>=', Carbon::now()->subDays($weaningAge));
        } elseif ($category === 'adult') {
            $query->where(function ($q) use ($weaningAge) {
                $q->whereNull('date_of_birth')
                    ->orWhereDate('date_of_birth', '<', Carbon::now()->subDays($weaningAge));
            });
        }

        return $query->paginate(10, ['*'], 'archive_page')->withQueryString();
    }

    protected function archivedPigletDeaths()
    {
        return PigletDeath::onlyTrashed()
            ->with(['farrowing.sow', 'recordedBy'])
            ->orderByDesc('deleted_at')
            ->paginate(10, ['*'], 'piglet_death_page')
            ->withQueryString();
    }

    protected function users(Request $request)
    {
        if (! $request->user()->isSuperuser() && ! $request->user()->isAdmin()) {
            return User::query()
                ->whereKey($request->user()->id)
                ->get(['id', 'name', 'email']);
        }

        $query = User::query()->orderBy('name');

        if (! $request->user()->isSuperuser() && $request->user()->organization_id) {
            $query->where('organization_id', $request->user()->organization_id);
        }

        return $query->get(['id', 'name', 'email']);
    }

    protected function scopeActivityForViewer($query, User $user): void
    {
        if ($user->isSuperuser()) {
            return;
        }

        // Farm administrators oversee the complete audit history for their
        // own organization. Every other staff role sees only actions performed
        // by that individual account. Organization-less legacy/platform rows
        // are never exposed to a farm user.
        $query->where('organization_id', $user->organization_id);

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }
    }

    protected function modules(): array
    {
        return [
            'stock' => 'Stock',
            'purchased_stock' => 'Purchased Stock',
            'sales' => 'Sales',
            'mortality' => 'Mortality',
            'finance' => 'Finance',
            'rooms' => 'Rooms',
            'users' => 'Users',
        ];
    }

    protected function actions(): array
    {
        return [
            'created' => 'Created',
            'updated' => 'Updated',
            'approved' => 'Approved',
            'added_to_stock' => 'Added to Stock',
            'archived' => 'Archived',
            'restored' => 'Restored',
            'deleted' => 'Deleted',
            'death_recorded' => 'Death Recorded',
            'sold' => 'Sold',
            'received' => 'Received',
            'quarantined' => 'Quarantined',
        ];
    }
}
