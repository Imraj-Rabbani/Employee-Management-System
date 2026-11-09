<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One earlier job of an applicant. Same columns as EmployeeWorkHistory, without a file. [REC-03]
 *
 * @property int $id
 * @property int $applicant_id
 * @property string $organization
 * @property string|null $designation
 * @property Carbon|null $from_date
 * @property Carbon|null $to_date null = still working there
 * @property string|null $leaving_reason
 */
class ApplicantWorkHistory extends TenantModel
{
    protected $fillable = ['organization', 'designation', 'from_date', 'to_date', 'leaving_reason'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date'];
    }

    /** @return BelongsTo<Applicant, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }
}
