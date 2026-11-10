<?php

namespace App\Modules\Hrm\Actions;

use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Enums\OfferStatus;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\JobOffer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Everything that changes a job offer, except the approval itself (that is the workflow: JobOffer::onApproved()).
 * [REC-06]
 *
 *     $offers = app(MakeOffer::class);
 *     $offers->problem($application)                       can an offer be made now?
 *     $offers->create($application, [...], auth()->id())   a draft
 *     $offers->update($offer, [...])                       draft / sent back only
 *     $offers->letterSent($offer)                          after the letter was emailed (6.7d)
 *     $offers->accept($offer, $date)                       the applicant said yes → 6.8 can hire
 *     $offers->decline($offer, $date, $reasonId, $note)    said no → the application is WITHDRAWN with that reason
 *
 * Rules: the application must still be open and have no "live" offer (one that is not declined / not
 * rejected by an approver). An answer can only be recorded for an approved offer, once.
 */
class MakeOffer
{
    public function __construct(private MoveApplication $move) {}

    public function problem(Application $application): ?string
    {
        if ($application->stage->isFinal()) {
            return __('This application is closed (:stage).', ['stage' => $application->stage->label()]);
        }

        $live = $application->offers()->get()->first(fn (JobOffer $o) => ! $o->status()->isDead());

        return $live ? __('There is already an offer (:no, :status).', ['no' => $live->number(), 'status' => mb_strtolower($live->status()->label())]) : null;
    }

    /** @param  array<string, mixed>  $values  gross_salary, grade_id, designation_id, employment_type_id, joining_date, expires_on, terms */
    public function create(Application $application, array $values, ?int $byUserId = null): JobOffer
    {
        if ($problem = $this->problem($application)) {
            throw new LogicException($problem);
        }

        return $application->offers()->create([...$this->only($values), 'created_by_user_id' => $byUserId]);
    }

    /** @param  array<string, mixed>  $values */
    public function update(JobOffer $offer, array $values): JobOffer
    {
        if (! $offer->isEditable()) {
            throw new LogicException(__('This offer can no longer be changed (:status).', ['status' => $offer->status()->label()]));
        }

        $offer->update($this->only($values));

        return $offer;
    }

    public function letterSent(JobOffer $offer): void
    {
        $this->mustBeApproved($offer);
        $offer->forceFill(['letter_sent_at' => now()])->save();
    }

    public function accept(JobOffer $offer, Carbon|string $on): void
    {
        $this->mustBeApproved($offer);
        $offer->forceFill(['response' => JobOffer::ACCEPTED, 'responded_on' => Carbon::parse($on)->toDateString()])->save();
    }

    /** Declined: the offer is over and the application is withdrawn (reason required, kept in its history). */
    public function decline(JobOffer $offer, Carbon|string $on, ?int $reasonId, ?string $note = null, ?int $byUserId = null): void
    {
        $this->mustBeApproved($offer);
        $application = $offer->application()->firstOrFail();

        if ($problem = $this->move->problem($application, ApplicationStage::Withdrawn, $reasonId, byHand: false)) {
            throw new LogicException($problem['message']);
        }

        DB::connection('tenant')->transaction(function () use ($offer, $on, $application, $reasonId, $note, $byUserId) {
            $offer->forceFill(['response' => JobOffer::DECLINED, 'responded_on' => Carbon::parse($on)->toDateString()])->save();
            $this->move->bySystem($application, ApplicationStage::Withdrawn, $reasonId,
                trim(__('Offer :no declined.', ['no' => $offer->number()]).' '.$note), $byUserId);
        });
    }

    private function mustBeApproved(JobOffer $offer): void
    {
        if (! in_array($offer->status(), [OfferStatus::Approved, OfferStatus::LetterSent], true)) {
            throw new LogicException(__('Only an approved offer that has not been answered yet can be sent or answered.'));
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function only(array $values): array
    {
        $values = array_intersect_key($values, array_flip(['gross_salary', 'grade_id', 'designation_id', 'employment_type_id', 'joining_date', 'expires_on', 'terms']));

        return array_map(fn ($v) => $v === '' ? null : $v, $values);
    }
}
