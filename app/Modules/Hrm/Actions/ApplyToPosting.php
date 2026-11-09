<?php

namespace App\Modules\Hrm\Actions;

use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Models\Applicant;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\JobPosting;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Puts an applicant on a job posting's list (stage "applied"). [REC-03]
 * The ONLY place that creates applications, so the rules below hold everywhere (forms, tests, 6.8 ...).
 *
 *     $action = app(ApplyToPosting::class);
 *     if ($problem = $action->problem($applicant, $posting)) { ...show $problem... }
 *     $action->handle($applicant, $posting, auth()->id());
 *
 * Rules: the posting must be taking applications today (Open, inside its dates), and a person applies
 * to a posting only once (also a unique index in the database, in case two clicks race each other).
 */
class ApplyToPosting
{
    /** Why this application is not allowed, or null if it is fine. */
    public function problem(Applicant $applicant, JobPosting $posting): ?string
    {
        if (! $posting->isAcceptingApplications()) {
            return __(':no is not taking applications (:state).', [
                'no' => $posting->number(),
                'state' => $posting->applicationWindow(),
            ]);
        }

        if ($applicant->exists && $posting->applications()->where('applicant_id', $applicant->id)->exists()) {
            return __(':name has already applied to :no.', ['name' => $applicant->name, 'no' => $posting->number()]);
        }

        return null;
    }

    public function handle(Applicant $applicant, JobPosting $posting, ?int $byUserId = null): Application
    {
        if (($problem = $this->problem($applicant, $posting)) !== null) {
            throw new LogicException($problem);
        }

        // The application and the first row of its history (→ applied) are saved together.
        return DB::connection('tenant')->transaction(function () use ($applicant, $posting, $byUserId) {
            $application = Application::create([
                'applicant_id' => $applicant->id,
                'job_posting_id' => $posting->id,
                'applied_on' => today()->toDateString(),
                'created_by_user_id' => $byUserId,
            ]);
            $application->forceFill(['stage_changed_at' => now()])->save();
            $application->stageChanges()->create([
                'from_stage' => null, 'to_stage' => ApplicationStage::Applied, 'changed_by_user_id' => $byUserId,
            ]);

            return $application;
        });
    }
}
