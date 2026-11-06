<?php

namespace App\Modules\Workflow\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\OrgUnit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Who approves one approval type, for which documents (legacy `workflows` header). [WF-02]
 * Empty scope columns mean "any". The engine picks the most specific match (task 3.3).
 *
 * @property int $id
 * @property int $approval_type_id
 * @property string $name
 * @property int|null $scope_org_unit_id
 * @property int|null $scope_employment_type_id
 * @property string|null $min_amount
 * @property bool $send_email
 * @property bool $allow_review
 * @property bool $allow_reject
 * @property bool $is_active
 */
class Workflow extends TenantModel
{
    protected $fillable = [
        'approval_type_id', 'name', 'scope_org_unit_id', 'scope_employment_type_id', 'min_amount',
        'send_email', 'allow_review', 'allow_reject', 'is_active',
    ];

    protected $attributes = ['send_email' => true, 'allow_review' => true, 'allow_reject' => true, 'is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scope_org_unit_id' => 'integer',
            'scope_employment_type_id' => 'integer',
            'min_amount' => 'decimal:2',
            'send_email' => 'boolean',
            'allow_review' => 'boolean',
            'allow_reject' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<ApprovalType, $this> */
    public function approvalType(): BelongsTo
    {
        return $this->belongsTo(ApprovalType::class);
    }

    /** @return BelongsTo<OrgUnit, $this> */
    public function scopeOrgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'scope_org_unit_id');
    }

    /** @return BelongsTo<EmploymentType, $this> */
    public function scopeEmploymentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class, 'scope_employment_type_id');
    }

    /**
     * Steps in level order: 1, 2, 3 ...
     *
     * @return HasMany<WorkflowStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('level');
    }
}
