<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One degree / exam of an employee (legacy `academics`). [EMP-03]
 *
 * @property int $id
 * @property int $employee_id
 * @property string $degree
 * @property string|null $institution
 * @property string|null $subject
 * @property string|null $score decimal as string, e.g. "3.75"
 * @property string|null $out_of e.g. "4.00"
 * @property int|null $passing_year
 * @property string|null $attachment_path
 * @property string|null $attachment_name
 */
class EmployeeEducation extends TenantModel
{
    /** Set by hand: Laravel treats "education" as uncountable and would look for `employee_education`. */
    protected $table = 'employee_educations';

    protected $fillable = ['degree', 'institution', 'subject', 'score', 'out_of', 'passing_year', 'attachment_path', 'attachment_name'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['score' => 'decimal:2', 'out_of' => 'decimal:2', 'passing_year' => 'integer'];
    }

    /** "3.75 / 4" or "65 / 100", or just "3.75". */
    public function result(): ?string
    {
        if ($this->score === null) {
            return null;
        }
        $trim = fn (string $n) => rtrim(rtrim($n, '0'), '.');

        return $trim($this->score).($this->out_of !== null ? ' / '.$trim($this->out_of) : '');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
