<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Actions\MoveApplication;
use App\Modules\Hrm\Database\Factories\JobOfferFactory;
use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Enums\OfferStatus;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Workflow\Concerns\HasApprovals;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Contracts\ShowsApprovalDetails;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Models\Approval;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A job offer to an applicant: salary, grade, designation, joining date. Approved through the "job_offer"
 * workflow, then sent as a letter, then accepted or declined. [REC-06]
 *
 *     $offer->status()          OfferStatus, worked out from the columns + approval rows
 *     $offer->comparisons()     the salary against the grade band, the request's range, the expected salary
 *     $offer->submitForApproval($user)   (HasApprovals)
 *
 * Created / answered only through Hrm\Actions\MakeOffer. onApproved() moves the application to "Offered".
 *
 * @property int $id
 * @property int $application_id
 * @property string $gross_salary
 * @property int|null $grade_id
 * @property int $designation_id
 * @property int|null $employment_type_id
 * @property Carbon $joining_date
 * @property Carbon|null $expires_on
 * @property string|null $terms
 * @property Carbon|null $approved_at
 * @property Carbon|null $letter_sent_at
 * @property string|null $response 'accepted' / 'declined'
 * @property Carbon|null $responded_on
 * @property int|null $created_by_user_id
 * @property Carbon $created_at
 */
#[UseFactory(JobOfferFactory::class)]
class JobOffer extends TenantModel implements Approvable, ShowsApprovalDetails
{
    use HasApprovals;

    /** @use HasFactory<JobOfferFactory> */
    use HasFactory;

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    /** approved_at / letter_sent_at / response are set by the actions, never by a form. */
    protected $fillable = [
        'application_id', 'gross_salary', 'grade_id', 'designation_id', 'employment_type_id',
        'joining_date', 'expires_on', 'terms', 'created_by_user_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'gross_salary' => 'decimal:2',
            'joining_date' => 'date',
            'expires_on' => 'date',
            'approved_at' => 'datetime',
            'letter_sent_at' => 'datetime',
            'responded_on' => 'date',
        ];
    }

    // ---- relations ---------------------------------------------------------------------------

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<Grade, $this> */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /** @return BelongsTo<Designation, $this> */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /** @return BelongsTo<EmploymentType, $this> */
    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    // ---- status ------------------------------------------------------------------------------

    public function status(): OfferStatus
    {
        if ($this->response !== null) {
            return $this->response === self::ACCEPTED ? OfferStatus::Accepted : OfferStatus::Declined;
        }
        if ($this->letter_sent_at !== null) {
            return OfferStatus::LetterSent;
        }
        if ($this->approved_at !== null) {
            return OfferStatus::Approved;
        }

        return match ($this->approvalStatus()) {
            null => OfferStatus::Draft,
            ApprovalStatus::Pending => OfferStatus::PendingApproval,
            ApprovalStatus::Review => OfferStatus::SentBack,
            ApprovalStatus::Rejected => OfferStatus::Rejected,
            ApprovalStatus::Approved => OfferStatus::Approved,   // (approved_at not yet refreshed)
        };
    }

    /** A draft, or sent back for changes: the form may change it and it may be sent (again). */
    public function isEditable(): bool
    {
        return in_array($this->status(), [OfferStatus::Draft, OfferStatus::SentBack], true);
    }

    /** "OFR-0007" */
    public function number(): string
    {
        return 'OFR-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    // ---- salary comparison -------------------------------------------------------------------

    /**
     * The offered salary against what it should be compared with. Each row:
     *   label   "Grade G5 band"
     *   range   "30,000 – 45,000" (or a single amount)
     *   verdict 'below' / 'inside' / 'above', or null when there is nothing to compare with
     * "Comparable employees" comes after Phase 9.4 (employee salaries).
     *
     * @return list<array{label: string, range: string|null, verdict: string|null}>
     */
    public function comparisons(): array
    {
        $salary = (float) $this->gross_salary;
        $request = $this->application?->jobPosting?->manpowerRequest;
        $applicant = $this->application?->applicant;

        $band = fn (?string $min, ?string $max): ?string => match (true) {
            $min === null && $max === null => null,
            $min !== null && $salary < (float) $min => 'below',
            $max !== null && $salary > (float) $max => 'above',
            default => 'inside',
        };

        return [
            [
                'label' => $this->grade ? __('Grade :grade band', ['grade' => $this->grade->name]) : __('Grade band'),
                'range' => $this->grade?->salaryRange(),
                'verdict' => $this->grade ? $band($this->grade->salary_min, $this->grade->salary_max) : null,
            ],
            [
                'label' => $request ? __('Approved in :no', ['no' => $request->number()]) : __('Manpower request'),
                'range' => $request?->salaryRange(),
                'verdict' => $request ? $band($request->salary_min, $request->salary_max) : null,
            ],
            [
                'label' => __('Applicant expects'),
                'range' => $applicant?->expected_salary !== null ? number_format((float) $applicant->expected_salary) : null,
                'verdict' => $applicant?->expected_salary !== null
                    ? match ($salary <=> (float) $applicant->expected_salary) {
                        -1 => 'below', 0 => 'inside', 1 => 'above'
                    }
                    : null,
            ],
        ];
    }

    // ---- Approvable --------------------------------------------------------------------------

    public function approvalType(): string
    {
        return 'job_offer';
    }

    public function approvalSubject(): string
    {
        return __('Job offer :no: :name, :designation, :salary', [
            'no' => $this->number(),
            'name' => $this->application->applicant->name ?? '?',
            'designation' => $this->designation->name ?? '?',
            'salary' => number_format((float) $this->gross_salary),
        ]);
    }

    /**
     * org unit / employment type: of the job (via the manpower request), so offers can have their own
     * workflow per department. employee = the HR user who made the offer. amount = monthly gross, so a
     * workflow with a min_amount can add a level for high salaries.
     */
    public function approvalContext(): array
    {
        $creator = $this->created_by_user_id === null ? null
            : Employee::withoutGlobalScope(VisibleToCurrentUser::class)->where('user_id', $this->created_by_user_id)->value('id');

        return [
            'employee_id' => $creator,
            'org_unit_id' => $this->application?->jobPosting?->manpowerRequest?->org_unit_id,
            'employment_type_id' => $this->employment_type_id,
            'amount' => (float) $this->gross_salary,
        ];
    }

    /** Last level approved: the application moves to "Offered" (with a history row). */
    public function onApproved(Approval $final): void
    {
        $this->forceFill(['approved_at' => now()])->save();

        $application = $this->application()->firstOrFail();
        if ($application->stage !== ApplicationStage::Offered) {
            app(MoveApplication::class)->bySystem($application, ApplicationStage::Offered, note: __('Offer :no approved', ['no' => $this->number()]), byUserId: $final->responded_by);
        }
    }

    /** Not approved: the application stays where it was; HR may make a new offer. */
    public function onRejected(Approval $final): void {}

    public function approvalDetailsView(): string
    {
        return 'hrm::approvals.job-offer';
    }
}
