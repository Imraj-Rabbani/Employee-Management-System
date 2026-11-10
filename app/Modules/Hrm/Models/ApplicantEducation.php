<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One degree / exam of an applicant. Same columns as EmployeeEducation, without a certificate file,
 * so 6.8 can copy them to the new employee as they are. [REC-03]
 *
 * @property int $id
 * @property int $applicant_id
 * @property string $degree
 * @property string|null $institution
 * @property string|null $subject
 * @property string|null $score
 * @property string|null $out_of
 * @property int|null $passing_year
 */
class ApplicantEducation extends TenantModel
{
    /** "education" is uncountable, so Laravel would guess "applicant_education". */
    protected $table = 'applicant_educations';

    protected $fillable = ['degree', 'institution', 'subject', 'score', 'out_of', 'passing_year'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['score' => 'decimal:2', 'out_of' => 'decimal:2', 'passing_year' => 'integer'];
    }

    /** @return BelongsTo<Applicant, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /** "3.75 / 4" or "78 / 100"; null when no score. */
    public function result(): ?string
    {
        if ($this->score === null) {
            return null;
        }
        $trim = fn (string $n) => rtrim(rtrim($n, '0'), '.');

        return $trim($this->score).($this->out_of !== null ? ' / '.$trim($this->out_of) : '');
    }
}
