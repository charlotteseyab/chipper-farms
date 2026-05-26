<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The single source of truth for "which organization is the current request
 * operating in".
 *
 * Every piece of application code that needs an organization context should go
 * through this resolver rather than reading auth()->user()->organization_id
 * directly. Reasons:
 *
 *   - Tests and CLI jobs can bind a specific organization without needing
 *     an authenticated request.
 *   - Future migration to database-per-tenant (stancl/tenancy) only touches
 *     this resolver and the middleware, not every controller.
 *   - Keeps a clean boundary between the "how is org resolved" concern and
 *     the "what does the business logic do with it" concern.
 *
 * Registered as a singleton in AppServiceProvider.
 */
class CurrentOrganization
{
    protected ?Organization $organization = null;
    protected bool $resolved = false;

    /**
     * Explicitly bind an organization to the current context.
     *
     * Used by:
     *  - The EnsureOrganizationContext middleware when an authenticated user is present.
     *  - Artisan commands that need to operate in a specific org (pass --organization).
     *  - Tests that want to set up a known org context.
     */
    public function bind(?Organization $organization): void
    {
        $this->organization = $organization;
        $this->resolved = true;
    }

    /**
     * Resolve the current organization, preferring the explicitly bound one,
     * and falling back to the authenticated user's organization.
     *
     * Returns null if nothing is resolvable (unauthenticated CLI, for example).
     * Callers that require an organization should use {@see requireId()}.
     */
    public function get(): ?Organization
    {
        if ($this->resolved) {
            return $this->organization;
        }

        $user = auth()->user();

        if ($user instanceof User && $user->organization_id) {
            $this->organization = $user->organization;
            $this->resolved = true;
            return $this->organization;
        }

        $this->resolved = true;
        return null;
    }

    /**
     * Convenience: just the id. Common in query scopes and foreign-key writes.
     */
    public function id(): ?int
    {
        return $this->get()?->id;
    }

    /**
     * Require a resolved organization id or throw. Use this in code paths
     * that cannot proceed without tenant context (writes to business tables,
     * finance posting, scoped reports).
     */
    public function requireId(): int
    {
        $id = $this->id();

        if ($id === null) {
            throw new \RuntimeException(
                'No organization context is resolved. Authenticated user must have an organization_id, '
                . 'or an organization must be explicitly bound via CurrentOrganization::bind().'
            );
        }

        return $id;
    }

    /**
     * Reset resolver state. Primarily used in tests between scenarios.
     */
    public function reset(): void
    {
        $this->organization = null;
        $this->resolved = false;
    }
}