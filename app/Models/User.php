<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    public const ALLOWED_ROLE_NAMES = ['superuser', 'admin', 'farmmanager', 'dataentry', 'accountant'];

    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
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
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
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
        ]);
    }

    protected function roleName(): ?string
    {
        // First try to get role from relationship if loaded.
        // Note: this app also has a string column named "role", so `$this->role` can be a string.
        if ($this->relationLoaded('role')) {
            $related = $this->getRelation('role');
            if (is_object($related) && isset($related->name)) {
                return $related->name;
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
}