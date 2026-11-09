<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Database\Factories\RejectionReasonFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Why an application was rejected or withdrawn, e.g. "Salary expectation too high". [REC-04]
 * Picked in the stage change form (6.5) when ApplicationStage::needsReason() is true.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 */
#[UseFactory(RejectionReasonFactory::class)]
class RejectionReason extends TenantModel
{
    /** @use HasFactory<RejectionReasonFactory> */
    use HasFactory;

    protected $fillable = ['name', 'is_active'];

    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
