<?php

namespace App\Modules\Workflow\Models;

use App\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of document that needs approval, e.g. "leave" (legacy `approvables`). [WF-01]
 *
 *     ApprovalType::forKey('leave')
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $model_class
 * @property bool $is_chained
 * @property bool $is_active
 */
class ApprovalType extends TenantModel
{
    protected $fillable = ['key', 'name', 'model_class', 'is_chained', 'is_active'];

    protected $attributes = ['is_chained' => true, 'is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_chained' => 'boolean', 'is_active' => 'boolean'];
    }

    /** @return HasMany<Workflow, $this> */
    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class);
    }

    public static function forKey(string $key): ?self
    {
        return static::where('key', $key)->first();
    }
}
