<?php

namespace App\Modules\Core\Models;

use App\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One permission key granted to a role, e.g. "payroll.run.approve" (legacy `permissions`).
 *
 * @property int $id
 * @property int $role_id
 * @property string $permission
 */
class RolePermission extends TenantModel
{
    public $timestamps = false;

    protected $fillable = ['permission'];

    /** No log entry per row: Role::syncPermissions() writes ONE entry on the role (old list → new list). */
    protected function shouldLogEvent(string $eventName): bool
    {
        return false;
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
