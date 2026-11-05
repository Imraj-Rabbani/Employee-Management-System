<?php

namespace App\Modules\Core\Models;

use App\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which role a login has in this company. One row per user (user_id is unique).
 * user_id is the CENTRAL users.id (another database, so no real foreign key).
 *
 * @property int $id
 * @property int $user_id
 * @property int $role_id
 */
class UserRole extends TenantModel
{
    protected $fillable = ['user_id', 'role_id'];

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
