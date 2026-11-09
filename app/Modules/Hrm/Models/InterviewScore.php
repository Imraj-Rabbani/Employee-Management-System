<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One panelist's score for one interview factor. [REC-05]
 * `weight` is the factor's weight when the score was given, so later setup changes do not rewrite results.
 *
 * @property int $id
 * @property int $interview_panelist_id
 * @property int $interview_factor_id
 * @property int $score 1 … InterviewScoring::MAX
 * @property string $weight decimal as a string, e.g. "25.00"
 * @property string|null $note
 */
class InterviewScore extends TenantModel
{
    protected $fillable = ['interview_panelist_id', 'interview_factor_id', 'score', 'weight', 'note'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['score' => 'integer', 'weight' => 'decimal:2'];
    }

    /** @return BelongsTo<InterviewPanelist, $this> */
    public function panelist(): BelongsTo
    {
        return $this->belongsTo(InterviewPanelist::class, 'interview_panelist_id');
    }

    /** @return BelongsTo<InterviewFactor, $this> */
    public function factor(): BelongsTo
    {
        return $this->belongsTo(InterviewFactor::class, 'interview_factor_id');
    }
}
