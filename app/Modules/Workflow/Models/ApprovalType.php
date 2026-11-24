<?php

namespace App\Modules\Workflow\Models;

use App\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A kind of document that needs approval, e.g. "leave" (legacy `approvables`).
 * Registered by the CODE: the module that owns the document inserts its row in a migration
 * (e.g. the leave_applications migration adds "leave"). Users never create or rename types;
 * they only configure each type's workflow (Workflow → Workflow Setup).
 *
 *     ApprovalType::forKey('leave')?->workflow
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $model_class
 * @property bool $is_chained
 * @property bool $is_active
 * @property-read Workflow|null $workflow
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

    /**
     * This type's workflow (levels + settings), null until someone configures it.
     *
     * @return HasOne<Workflow, $this>
     */
    public function workflow(): HasOne
    {
        return $this->hasOne(Workflow::class);
    }

    /**
     * 'active' (on, with levels: documents can be sent), 'off' (switched off on the screen) or
     * 'none' (not set up yet / no levels). Load `workflow.steps` first in lists.
     */
    public function workflowStatus(): string
    {
        $workflow = $this->workflow;

        return match (true) {
            $workflow === null || ($workflow->is_active && $workflow->steps->isEmpty()) => 'none',
            $workflow->is_active => 'active',
            default => 'off',
        };
    }

    public function workflowStatusLabel(): string
    {
        return match ($this->workflowStatus()) {
            'active' => __('Active'),
            'off' => __('Off'),
            default => __('Not set up'),
        };
    }

    public static function forKey(string $key): ?self
    {
        return static::where('key', $key)->first();
    }
}
