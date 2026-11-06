<?php

namespace App\Modules\Core\Models;

use App\Core\Models\TenantModel;
use App\Models\User;
use App\Modules\Core\Services\Permissions;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A role of the current company (legacy "profile"). [FND-05]
 *
 *     $role->permissionKeys()                     ['hrm.employee.view', ...]
 *     $role->syncPermissions(['hrm.employee.view'])   replace its permissions
 *     $role->assignTo($user)                      give a login this role (replaces their old one)
 *     Role::administrator()                       the company's Administrator role
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_admin
 * @property int|null $org_unit_id
 */
class Role extends TenantModel
{
    /** is_admin is not fillable: only the seeder creates the Administrator role. */
    protected $fillable = ['name', 'description', 'org_unit_id'];

    protected $attributes = ['is_admin' => false];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_admin' => 'boolean'];
    }

    /** @return HasMany<RolePermission, $this> */
    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /** @return HasMany<UserRole, $this> */
    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public static function administrator(): ?self
    {
        return static::where('is_admin', true)->first();
    }

    /** @return list<string> */
    public function permissionKeys(): array
    {
        return $this->permissions()->orderBy('permission')->pluck('permission')->all();
    }

    /** @param  list<string>  $keys */
    public function syncPermissions(array $keys): void
    {
        $keys = array_values(array_unique($keys));
        sort($keys);
        $old = $this->permissionKeys();

        $this->getConnection()->transaction(function () use ($keys) {
            $this->permissions()->whereNotIn('permission', $keys)->delete();

            $existing = $this->permissions()->pluck('permission')->all();
            foreach (array_diff($keys, $existing) as $key) {
                $this->permissions()->create(['permission' => $key]);
            }
        });

        // One readable history entry on the role, instead of one per permission row.
        if ($old !== $keys) {
            activity()->performedOn($this)->event('updated')
                ->withChanges(['old' => ['permissions' => $old], 'attributes' => ['permissions' => $keys]])
                ->log('updated');
        }

        app(Permissions::class)->flush();
    }

    public function assignTo(User $user): void
    {
        UserRole::updateOrCreate(['user_id' => $user->id], ['role_id' => $this->id]);

        app(Permissions::class)->flush();
    }
}
