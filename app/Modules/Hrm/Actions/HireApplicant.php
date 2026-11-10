<?php

namespace App\Modules\Hrm\Actions;

use App\Core\Support\TenantUploads;
use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Enums\DocumentType;
use App\Modules\Hrm\Enums\OfferStatus;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\JobOffer;
use App\Modules\Hrm\Models\ManpowerRequest;
use App\Modules\Hrm\Models\Position;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * Hire: turns an ACCEPTED offer into a new employee. [REC-07] The last step of recruitment.
 *
 *     $hire = app(HireApplicant::class);
 *     $hire->defaults($offer)                         what the Hire form starts with
 *     if ($problem = $hire->problem($offer, $values)) { ...show $problem['message'] under $problem['field']... }
 *     $employee = $hire->handle($offer, $values, auth()->id());
 *
 * handle(), in ONE transaction (all or nothing):
 *   1. the employee: personal details from the applicant, the job from the offer (designation, grade,
 *      employment type, joining date), the org unit / position from the manpower request, Active.
 *      (Employee::created() adds the first org-unit history row, as for any new employee.)
 *   2. copies education + work history; the CV becomes a document "CV" (a copy of the file)
 *   3. the position seat, if any, is filled
 *   4. the application → Hired (history row) and remembers the employee (hired_employee_id)
 *   5. the manpower request's hired_count + 1 (its openings go down)
 * Salary is NOT set here: employee salaries come in Phase 9 (the offer keeps the agreed gross).
 *
 * Rules: the offer is accepted, the application still at "Offered" and not hired before, the request
 * still has an opening, a unique employee code, and the position (if chosen) free.
 */
class HireApplicant
{
    public function __construct(private MoveApplication $move, private TenantUploads $uploads) {}

    /** @return array{code: string, join_date: string, org_unit_id: int, position_id: int|null, office_email: string|null} */
    public function defaults(JobOffer $offer): array
    {
        $request = $offer->application->jobPosting->manpowerRequest;
        $seat = $request->position_id ? Position::find($request->position_id) : null;

        return [
            'code' => '',
            'join_date' => $offer->joining_date->toDateString(),
            'org_unit_id' => $request->org_unit_id,
            'position_id' => $seat && $seat->employee_id === null ? $seat->id : null,   // only when still free
            'office_email' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $values  code, join_date, org_unit_id, position_id, office_email
     * @return array{field: string, message: string}|null
     */
    public function problem(JobOffer $offer, array $values): ?array
    {
        $application = $offer->application;
        $request = $application->jobPosting->manpowerRequest;

        $message = match (true) {
            $offer->status() !== OfferStatus::Accepted => __('Only an accepted offer can be hired (this one: :status).', ['status' => $offer->status()->label()]),
            $application->hired_employee_id !== null => __('This applicant has already been hired.'),
            $application->stage !== ApplicationStage::Offered => __('The application is not at "Offered" (it is :stage).', ['stage' => $application->stage->label()]),
            $request->openings() < 1 => __(':no is already filled (:hired of :headcount hired).', ['no' => $request->number(), 'hired' => $request->hired_count, 'headcount' => $request->headcount]),
            default => null,
        };
        if ($message !== null) {
            return ['field' => 'code', 'message' => $message];
        }

        $employees = Employee::withoutGlobalScope(VisibleToCurrentUser::class);
        if (trim((string) ($values['code'] ?? '')) === '' || $employees->clone()->where('code', $values['code'])->exists()) {
            return ['field' => 'code', 'message' => __('Give an employee code that is not used yet.')];
        }
        if (! empty($values['office_email']) && $employees->clone()->where('office_email', $values['office_email'])->exists()) {
            return ['field' => 'office_email', 'message' => __('This office email is already used by another employee.')];
        }
        if (! empty($values['position_id'])) {
            $seat = Position::find($values['position_id']);
            if ($seat === null || $seat->employee_id !== null) {
                return ['field' => 'position_id', 'message' => __('This position is already held by another employee.')];
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $values */
    public function handle(JobOffer $offer, array $values, ?int $byUserId = null): Employee
    {
        $offer->loadMissing(['application.applicant.educations', 'application.applicant.workHistories', 'application.jobPosting.manpowerRequest']);
        if ($problem = $this->problem($offer, $values)) {
            throw new LogicException($problem['message']);
        }

        $application = $offer->application;
        $applicant = $application->applicant;

        // The CV is copied first (files are not part of a database transaction); removed again if anything fails.
        $cvCopy = $applicant->cv_path && $this->uploads->exists($applicant->cv_path)
            ? $this->uploads->copy($applicant->cv_path, 'employees/documents') : null;

        try {
            return DB::connection('tenant')->transaction(function () use ($offer, $values, $byUserId, $application, $applicant, $cvCopy) {
                $employee = Employee::create([
                    'code' => trim((string) $values['code']),
                    'first_name' => $applicant->first_name,
                    'last_name' => $applicant->last_name,
                    'gender' => $applicant->gender,
                    'birth_date' => $applicant->birth_date,
                    'national_id' => $applicant->national_id,
                    'mobile' => $applicant->phone,
                    'personal_email' => $applicant->email,
                    'office_email' => ($values['office_email'] ?? '') ?: null,
                    'join_date' => $values['join_date'] ?? $offer->joining_date,
                    'org_unit_id' => $values['org_unit_id'],
                    'position_id' => ($values['position_id'] ?? '') ?: null,
                    'designation_id' => $offer->designation_id,
                    'grade_id' => $offer->grade_id,
                    'employment_type_id' => $offer->employment_type_id,
                ]);

                foreach ($applicant->educations as $education) {
                    $employee->educations()->create($education->only(['degree', 'institution', 'subject', 'score', 'out_of', 'passing_year']));
                }
                foreach ($applicant->workHistories as $job) {
                    $employee->workHistories()->create($job->only(['organization', 'designation', 'from_date', 'to_date', 'leaving_reason']));
                }
                if ($cvCopy !== null) {
                    $employee->documents()->create([
                        'type' => DocumentType::Other, 'title' => __('CV'),
                        'attachment_path' => $cvCopy, 'attachment_name' => $applicant->cv_name,
                    ]);
                }

                if ($employee->position_id !== null) {
                    Position::whereKey($employee->position_id)->update(['employee_id' => $employee->id, 'is_vacant' => false]);
                }

                $this->move->bySystem($application, ApplicationStage::Hired, note: __('Hired as :code', ['code' => $employee->code]), byUserId: $byUserId);
                $application->forceFill(['hired_employee_id' => $employee->id])->save();

                // lockForUpdate: two hires at the same moment cannot both take the last opening.
                $request = ManpowerRequest::whereKey($application->jobPosting->manpowerRequest->id)->lockForUpdate()->firstOrFail();
                if ($request->openings() < 1) {
                    throw new LogicException(__(':no is already filled.', ['no' => $request->number()]));
                }
                $request->forceFill(['hired_count' => $request->hired_count + 1])->save();

                return $employee;
            });
        } catch (Throwable $e) {
            $this->uploads->delete($cvCopy);

            throw $e;
        }
    }
}
