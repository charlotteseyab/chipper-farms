<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            // A superuser is a platform identity, never a member of a tenant.
            if (static::normalizeRoleName($user->getAttribute('role')) === 'superuser') {
                $user->organization_id = null;
            }
        });
    }

    /**
     * Roles a user may actually hold.
     *
     * `mill_operator` was added when IMP-118 was closed. It is not a new product
     * role — the rest of the system already assumed it existed:
     *
     *   - `FarmOperationsPermissionsSeeder` and `Phase4InventoryPermissionsSeeder`
     *     provision it with feed, raw-material and inventory-value permissions;
     *   - `EnsureFinanceAccess` documents it in its role matrix as holding one
     *     finance-module permission;
     *   - `NavigationHelper::dashboardItem()` was made total specifically because
     *     a role outside this list would 500 every page that renders the layout,
     *     and mill_operator was named as the role that did it.
     *
     * So the seeders granted it five permissions, the middleware planned for it,
     * the navigation was hardened for it — and the one thing missing was its
     * name here, which meant no user could ever hold it and every one of those
     * grants reached nobody. Adding the name completes a role the codebase was
     * already built around rather than inventing one.
     */
    public const ALLOWED_ROLE_NAMES = ['superuser', 'admin', 'farmmanager', 'dataentry', 'accountant', 'treasurer', 'salesperson', 'customer', 'mill_operator'];

    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;
    use BelongsToOrganization;
    use HasPermissions;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'role_id',
        'organization_id',
        'first_name',
        'last_name',
        'phone',
        'active_status',
        'onboarding_required',
        'onboarding_completed_at',
        'last_seen',
        'last_active_at'
    ];

    protected $attributes = [
        'active_status' => true,
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_seen' => 'datetime',
            'last_active_at' => 'datetime',
            'active_status' => 'boolean',
            'onboarding_required' => 'boolean',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    /**
     * The user's role record. Only for queries (`whereHas('role', …)`,
     * `role()->first()`): never eager-load it. `role` is also a text column
     * on users, and `$user->role` always returns that text, so anything that
     * loads this relation and then reads `$user->role` gets a string. The
     * queue does exactly that when it restores a queued user with
     * `role.permissions` loaded: "relationLoaded() on string", on every
     * retry. Eager-load roleRecord() instead.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /** The role record under a name no column shares: the one to eager-load. */
    public function roleRecord()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Queued jobs and notifications carry users without their loaded
     * relations, so a user can never be restored with a relation the queue
     * cannot rebuild (see role()). The job reloads what it needs.
     */
    public function getQueueableRelations()
    {
        return [];
    }


    // Role helpers
    public static function allowedRoleNames(): array
    {
        return self::ALLOWED_ROLE_NAMES;
    }

    public static function normalizeRoleName(?string $role): ?string
    {
        if ($role === null) {
            return null;
        }

        $normalized = strtolower(trim($role));

        return match ($normalized) {
            'manager' => 'farmmanager',
            'data_entry', 'data-entry' => 'dataentry',
            'accounts', 'accounting' => 'accountant',
            'sales', 'sales_person', 'sales-person' => 'salesperson',
            default => $normalized,
        };
    }

    public function hasRoleName(string $role): bool
    {
        return $this->roleName() === static::normalizeRoleName($role);
    }

    public function syncRoleFields(string $roleName): void
    {
        $normalized = static::normalizeRoleName($roleName);

        if (!in_array($normalized, static::allowedRoleNames(), true)) {
            throw new \InvalidArgumentException('Unsupported role: ' . $roleName);
        }

        $role = Role::firstOrCreate(['name' => $normalized]);

        $this->forceFill([
            'role' => $normalized,
            'role_id' => $role->id,
            'organization_id' => $normalized === 'superuser' ? null : $this->organization_id,
        ]);
    }

    protected function roleName(): ?string
    {
        // First try to get role from relationship if loaded.
        // Note: this app also has a string column named "role", so `$this->role` can be a string.
        foreach (['roleRecord', 'role'] as $relation) {
            if ($this->relationLoaded($relation)) {
                $related = $this->getRelation($relation);
                if (is_object($related) && isset($related->name)) {
                    return $related->name;
                }
            }
        }

        // Fall back to string column if present
        if (isset($this->attributes['role']) && is_string($this->attributes['role']) && !empty($this->attributes['role'])) {
            return static::normalizeRoleName($this->attributes['role']);
        }

        // Last resort: try to load role from database if role_id exists
        if ($this->role_id) {
            try {
                $role = Role::find($this->role_id);
                return $role ? $role->name : null;
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    public function isDataEntry(): bool
    {
        return $this->hasRoleName('dataentry');
    }

    public function isAdmin(): bool
    {
        return $this->hasRoleName('admin');
    }

    public function isManager(): bool
    {
        return $this->hasRoleName('farmmanager');
    }

    public function isSuperuser(): bool
    {
        return $this->hasRoleName('superuser');
    }

    public function isAccountant(): bool
    {
        return $this->hasRoleName('accountant');
    }

    public function isTreasurer(): bool
    {
        return $this->hasRoleName('treasurer');
    }

    public function isSalesperson(): bool
    {
        return $this->hasRoleName('salesperson');
    }

    public function isCustomer(): bool
    {
        return $this->hasRoleName('customer');
    }
}
