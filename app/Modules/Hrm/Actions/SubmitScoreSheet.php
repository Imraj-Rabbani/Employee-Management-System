<?php

namespace App\Modules\Hrm\Actions;

use App\Modules\Hrm\Enums\InterviewStatus;
use App\Modules\Hrm\Models\InterviewPanelist;
use LogicException;

/**
 * Sends (locks) a panelist's SAVED score sheet: from then on it counts in the results and cannot change.
 * The one place with the "ready to send?" rules, used by the score sheet (HR on someone's behalf) and by
 * Self Service → My Interviews, where an interviewer ticks several sheets and sends them at once.
 *
 *     $submit = app(SubmitScoreSheet::class);
 *     if ($problem = $submit->problem($panelist)) { ...show $problem... }
 *     $submit->handle($panelist);
 *
 * Ready = not sent yet, the interview is still planned, every question of the stage's question set has a score,
 * and a recommendation is chosen.
 */
class SubmitScoreSheet
{
    /** Why this sheet cannot be sent now, or null. */
    public function problem(InterviewPanelist $panelist): ?string
    {
        if ($panelist->isSubmitted()) {
            return __('Already sent.');
        }
        if ($panelist->interview->status !== InterviewStatus::Scheduled) {
            return __('The interview is :status.', ['status' => mb_strtolower($panelist->interview->status->label())]);
        }

        $scored = $panelist->scores()->pluck('interview_question_id')->all();
        if ($scored === []) {
            return __('Not scored yet.');
        }

        $items = $panelist->interview->stage?->questionSet?->items()->with('question')->get() ?? collect();
        $missing = $items->whereNotIn('interview_question_id', $scored)->map(fn ($item) => $item->question->name);
        if ($missing->isNotEmpty()) {
            return __('Not scored: :questions.', ['questions' => $missing->join(', ')]);
        }

        return $panelist->recommendation === null ? __('No recommendation chosen.') : null;
    }

    public function handle(InterviewPanelist $panelist): void
    {
        if (($problem = $this->problem($panelist)) !== null) {
            throw new LogicException($problem);
        }

        $panelist->forceFill(['submitted_at' => now()])->save();
    }
}
