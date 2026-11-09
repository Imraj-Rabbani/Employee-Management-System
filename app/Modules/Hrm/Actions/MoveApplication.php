<?php

namespace App\Modules\Hrm\Actions;

use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\ApplicationStageChange;
use App\Modules\Hrm\Models\RejectionReason;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Moves an application to another stage and writes the history row. [REC-04]
 * The ONLY place (besides ApplyToPosting's first row) that changes applications.stage.
 *
 *     $move = app(MoveApplication::class);
 *     $move->targetsFor($application)                         stages the "Move to" menu offers
 *     if ($problem = $move->problem($application, $to, $reasonId)) { ...show $problem['message'] under $problem['field']... }
 *     $move->handle($application, $to, $reasonId, 'Good CV', auth()->id());
 *
 * Rules:
 *   - hired / rejected / withdrawn are final: no more moves
 *   - forward only (applied → interview may skip shortlisted), never back
 *   - Offered and Hired are NOT chosen by hand: the offer (6.7) and the hire (6.8) set them through bySystem()
 *   - rejected / withdrawn need an ACTIVE rejection reason; other stages clear the reason
 */
class MoveApplication
{
    /** Stages HR can pick by hand. Offered / Hired come from 6.7 / 6.8. */
    public const MANUAL = [ApplicationStage::Shortlisted, ApplicationStage::Interview, ApplicationStage::Rejected, ApplicationStage::Withdrawn];

    /** @return list<ApplicationStage> where this application can go now (empty when it is closed) */
    public function targetsFor(Application $application): array
    {
        return array_values(array_filter(self::MANUAL, fn (ApplicationStage $to) => $this->problem($application, $to, reasonId: -1) === null));
    }

    /**
     * Why this move is not allowed, or null if it is fine. field = which input the message belongs to.
     * $reasonId -1 = "do not check the reason" (used by targetsFor(), before the user picked one).
     *
     * @return array{field: string, message: string}|null
     */
    public function problem(Application $application, ApplicationStage $to, ?int $reasonId = null, bool $byHand = true): ?array
    {
        $from = $application->stage;

        $message = match (true) {
            $from->isFinal() => __('This application is closed (:stage).', ['stage' => $from->label()]),
            $to === $from => __('The application is already at this stage.'),
            $byHand && ! in_array($to, self::MANUAL, true) => __(':stage is set by the offer or the hire, not by hand.', ['stage' => $to->label()]),
            $to->step() !== null && $to->step() < (int) $from->step() => __('An application cannot move back (:from → :to).', ['from' => $from->label(), 'to' => $to->label()]),
            default => null,
        };
        if ($message !== null) {
            return ['field' => 'stage', 'message' => $message];
        }

        if ($to->needsReason() && $reasonId !== -1
            && ($reasonId === null || ! RejectionReason::whereKey($reasonId)->where('is_active', true)->exists())) {
            return ['field' => 'rejection_reason_id', 'message' => __('Choose a reason.')];
        }

        return null;
    }

    public function handle(Application $application, ApplicationStage $to, ?int $reasonId = null, ?string $note = null, ?int $byUserId = null): ApplicationStageChange
    {
        return $this->apply($application, $to, $reasonId, $note, $byUserId, byHand: true);
    }

    /**
     * A move made by the system, not chosen in the Move menu: the offer's approval (→ Offered, 6.7) and the
     * hire (→ Hired, 6.8). Same rules and history as handle(), except Offered / Hired are allowed.
     */
    public function bySystem(Application $application, ApplicationStage $to, ?int $reasonId = null, ?string $note = null, ?int $byUserId = null): ApplicationStageChange
    {
        return $this->apply($application, $to, $reasonId, $note, $byUserId, byHand: false);
    }

    private function apply(Application $application, ApplicationStage $to, ?int $reasonId, ?string $note, ?int $byUserId, bool $byHand): ApplicationStageChange
    {
        if ($problem = $this->problem($application, $to, $reasonId, $byHand)) {
            throw new LogicException($problem['message']);
        }

        $reasonId = $to->needsReason() ? $reasonId : null;

        // Both or neither: the new stage and its history row.
        return DB::connection('tenant')->transaction(function () use ($application, $to, $reasonId, $note, $byUserId) {
            $change = $application->stageChanges()->create([
                'from_stage' => $application->stage,
                'to_stage' => $to,
                'rejection_reason_id' => $reasonId,
                'note' => $note === '' ? null : $note,
                'changed_by_user_id' => $byUserId,
            ]);

            $application->forceFill([
                'stage' => $to,
                'rejection_reason_id' => $reasonId,
                'stage_changed_at' => now(),
            ])->save();

            return $change;
        });
    }
}
