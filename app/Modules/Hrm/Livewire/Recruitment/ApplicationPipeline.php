<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Modules\Hrm\Actions\MoveApplication;
use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\RejectionReason;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A job posting's pipeline: its applications grouped by stage, and "Move" to change one's stage
 * (with a note, and a reason when rejecting / withdrawing). [REC-04]
 *
 * The rules are all in MoveApplication: the "Move to" list is its targetsFor(), and save asks its problem().
 * Permission: the posting page needs hrm.recruitment.manage (re-checked on every click); the posting is
 * looked up through visibleTo(), and an application always THROUGH the posting.
 */
class ApplicationPipeline extends Component
{
    #[Locked]
    public int $postingId;

    /** Which tab: 'open' (everything not closed), a stage value, or 'all'. In the URL: ?stage=interview */
    #[Url(as: 'stage')]
    public string $tab = 'open';

    // ---- the Move modal ------------------------------------------------------------------------

    #[Locked]
    public ?int $applicationId = null;

    public string $to = '';

    public int|string $reasonId = '';

    public string $note = '';

    public function startMove(int $applicationId): void
    {
        $application = $this->application($applicationId);

        $this->applicationId = $application->id;
        $this->to = '';
        $this->reasonId = '';
        $this->note = '';
        $this->resetValidation();

        Flux::modal('application-move')->show();
    }

    public function move(MoveApplication $move): void
    {
        $application = $this->application((int) $this->applicationId);
        $targets = array_map(fn (ApplicationStage $s) => $s->value, $move->targetsFor($application));

        $this->validate([
            'to' => ['required', Rule::in($targets)],
            'reasonId' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [], ['to' => __('stage')]);

        $to = ApplicationStage::from($this->to);
        $reasonId = $this->reasonId === '' ? null : (int) $this->reasonId;

        if ($problem = $move->problem($application, $to, $reasonId)) {
            $this->addError($problem['field'] === 'rejection_reason_id' ? 'reasonId' : 'to', $problem['message']);

            return;
        }

        $move->handle($application, $to, $reasonId, trim($this->note), Auth::id());

        Flux::modal('application-move')->close();
        $this->applicationId = null;
        $this->dispatch('toast', message: __(':name → :stage', ['name' => $application->applicant->name, 'stage' => $to->label()]), variant: 'success');
    }

    /** The interview form (another component) saved: draw again, the stage may have moved to Interview. */
    #[On('interview-saved')]
    public function refreshPipeline(): void {}

    private function posting(): JobPosting
    {
        return JobPosting::query()->visibleTo(Auth::user())->findOrFail($this->postingId);
    }

    /** Through the posting: an application of another posting gives 404. */
    private function application(int $id): Application
    {
        return $this->posting()->applications()->with('applicant')->findOrFail($id);
    }

    public function render(MoveApplication $move): View
    {
        $posting = $this->posting();

        $all = $posting->applications()
            ->with(['applicant', 'rejectionReason', 'interviews'])
            ->orderByDesc('stage_changed_at')->orderByDesc('id')
            ->get();

        // Counts per tab, from the one query above (no query per tab).
        $counts = ['open' => $all->filter(fn (Application $a) => ! $a->stage->isFinal())->count(), 'all' => $all->count()];
        foreach (ApplicationStage::cases() as $stage) {
            $counts[$stage->value] = $all->where('stage', $stage)->count();
        }

        $rows = match ($this->tab) {
            'all' => $all,
            'open' => $all->filter(fn (Application $a) => ! $a->stage->isFinal()),
            default => $all->filter(fn (Application $a) => $a->stage->value === $this->tab),
        };

        $moving = $this->applicationId ? $all->firstWhere('id', $this->applicationId) : null;

        return view('hrm::livewire.recruitment.application-pipeline', [
            'posting' => $posting,
            'rows' => $rows,
            'counts' => $counts,
            'moving' => $moving,
            'targets' => $moving ? collect($move->targetsFor($moving))->mapWithKeys(fn (ApplicationStage $s) => [$s->value => $s->label()])->all() : [],
            'needsReason' => $this->to !== '' && ApplicationStage::tryFrom($this->to)?->needsReason(),
            'reasons' => RejectionReason::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'canMove' => fn (Application $a) => $move->targetsFor($a) !== [],
        ]);
    }
}
