<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable // implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /** Logins always live in the central database (ems_clients). */
    protected $connection = 'central';

    /**
     * Defaults for a new User object, matching the column defaults in the migration.
     * (Without this, is_super_admin is null on a user just created in PHP, until it is re-read from the DB.)
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_super_admin' => false,
        'locale' => 'en',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'is_super_admin' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * This login's role in the CURRENT company (tenant DB, table user_roles), or null.
     * Null as well when no company is active or the user belongs to another company.
     */
    public function role(): ?Role
    {
        if ($this->tenant_id === null || tenant()?->id !== $this->tenant_id) {
            return null;
        }

        return UserRole::where('user_id', $this->id)->first()?->role;
    }

    /**
     * Platform owner: can open the /admin area (create companies, enable modules).
     * is_super_admin is deliberately not in $fillable: set it by hand, never from a form.
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    /**
     * The company this user belongs to. Null for platform users (super admin).
     *
     * tenant_id is deliberately not in $fillable: it must never come from a form.
     * Set it with $tenant->users()->create([...]) or $user->tenant()->associate($tenant).
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
