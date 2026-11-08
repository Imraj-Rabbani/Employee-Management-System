<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A job the employee had before joining (legacy `workhistory`). [EMP-03]
 *
 * @property int $id
 * @property int $employee_id
 * @property string $organization
 * @property string|null $designation
 * @property Carbon|null $from_date
 * @property Carbon|null $to_date
 * @property string|null $leaving_reason
 * @property string|null $attachment_path
 * @property string|null $attachment_name
 */
class EmployeeWorkHistory extends TenantModel
{
    protected $fillable = ['organization', 'designation', 'from_date', 'to_date', 'leaving_reason', 'attachment_path', 'attachment_name'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date'];
    }

    /** "2 years 3 months", or null when a date is missing. */
    public function duration(): ?string
    {
        if (! $this->from_date || ! $this->to_date) {
            return null;
        }

        return $this->from_date->diffForHumans($this->to_date->copy()->addDay(), ['syntax' => Carbon::DIFF_ABSOLUTE, 'parts' => 2]);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
