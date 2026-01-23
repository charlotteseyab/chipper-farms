<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

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
        'first_name',
        'last_name',
        'phone',
        'active_status',
        'last_seen',
        'last_active_at'
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
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }


    // Role helpers
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
            return $this->attributes['role'];
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
        return $this->roleName() === 'dataentry';
    }

    public function isAdmin(): bool
    {
        return $this->roleName() === 'admin';
    }

    public function isManager(): bool
    {
        return $this->roleName() === 'farmmanager';
    }

    public function isSuperuser(): bool
    {
        return $this->roleName() === 'superuser';
    }
}
