<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Modules\Hrm\Actions\MoveApplication;
use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Enums\InterviewStatus;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\RejectionReason;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A job posting's pipeline: its applications grouped by stage. Rows are ticked; the toolbar above the
 * table works on the ticked rows:
 *   History            exactly one row (ApplicationHistory modal)
 *   Schedule interview one or more: those that can get a round now (InterviewForm, several at once)
 *   Make offer         exactly one row that may get an offer (OfferForm)
 *   Move               one or more: every ticked row must be movable; "Move to" lists only the stages
 *                      ALL of them may go to. A row that fails its own check stays and is named.
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

    /** @var list<string> ticked applications (the toolbar works on these) */
    public array $selected = [];

    // ---- the Move modal ------------------------------------------------------------------------

    /** @var list<int> the applications being moved */
    #[Locked]
    public array $movingIds = [];

    public string $to = '';

    public int|string $reasonId = '';

    public string $note = '';

    /** A new tab shows other rows: start with nothing ticked, so nothing hidden is changed by mistake. */
    public function updatedTab(): void
    {
        $this->selected = [];
    }

    /** Toolbar "Move": the ticked rows. */
    public function startMove(MoveApplication $move): void
    {
        $applications = $this->applications($this->selected);
        if ($applications->isEmpty()) {
            return;
        }
        if ($this->commonTargets($applications, $move) === []) {
            $this->dispatch('toast', variant: 'error', message: __('The ticked applicants have no stage in common to move to. Tick fewer, or ones at the same stage.'));

            return;
        }

        $this->movingIds = $applications->pluck('id')->all();
        $this->to = '';
        $this->reasonId = '';
        $this->note = '';
        $this->resetValidation();

        Flux::modal('application-move')->show();
    }

    public function move(MoveApplication $move): void
    {
        $applications = $this->applications($this->movingIds);
        $targets = array_map(fn (ApplicationStage $s) => $s->value, $this->commonTargets($applications, $move));

        $this->validate([
            'to' => ['required', Rule::in($targets)],
            'reasonId' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [], ['to' => __('stage')]);

        $to = ApplicationStage::from($this->to);
        $reasonId = $this->reasonId === '' ? null : (int) $this->reasonId;

        // The reason is the same for all: check it once on the first, so a missing reason is shown on the field.
        if (($problem = $move->problem($applications->first(), $to, $reasonId)) && $problem['field'] === 'rejection_reason_id') {
            $this->addError('reasonId', $problem['message']);

            return;
        }

        $moved = [];
        $problems = [];
        foreach ($applications as $application) {
            if ($problem = $move->problem($application, $to, $reasonId)) {
                $problems[] = $application->applicant->name.': '.$problem['message'];

                continue;
            }
            $move->handle($application, $to, $reasonId, trim($this->note), Auth::id());
            $moved[] = $application->applicant->name;
        }

        Flux::modal('application-move')->close();
        $this->movingIds = [];
        $this->selected = [];
        $this->dispatch('toast',
            message: implode(' ', array_filter([
                $moved === [] ? null : __(':names → :stage', ['names' => implode(', ', $moved), 'stage' => $to->label()]),
                ...$problems,
            ])),
            variant: $problems === [] ? 'success' : ($moved === [] ? 'error' : 'warning'),
        );
    }

    /**
     * Stages EVERY one of these applications may be moved to by hand, in stage order.
     *
     * @param  Collection<int, Application>  $applications
     * @return list<ApplicationStage>
     */
    private function commonTargets(Collection $applications, MoveApplication $move): array
    {
        $common = null;
        foreach ($applications as $application) {
            $targets = $move->targetsFor($application);
            $common = $common === null ? $targets : array_values(array_filter($common, fn (ApplicationStage $s) => in_array($s, $targets, true)));
        }

        return $common ?? [];
    }

    /**
     * Applications of THIS posting with these ids (others are ignored), in pipeline order.
     *
     * @param  list<int|string>  $ids
     * @return Collection<int, Application>
     */
    private function applications(array $ids): Collection
    {
        return $this->posting()->applications()->with('applicant')->whereKey(array_map('intval', $ids))
            ->orderByDesc('stage_changed_at')->orderByDesc('id')->get();
    }

    /** The interview form (another component) saved: draw again, the stage may have moved to Interview. */
    #[On('interview-saved')]
    #[On('applicants-imported')]
    public function refreshPipeline(): void
    {
        $this->selected = [];
    }

    private function posting(): JobPosting
    {
        return JobPosting::query()->visibleTo(Auth::user())->findOrFail($this->postingId);
    }

    public function render(MoveApplication $move): View
    {
        $posting = $this->posting();

        $all = $posting->applications()
            ->with(['applicant', 'rejectionReason', 'interviews', 'offers.approvals'])
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

        $moving = $all->whereIn('id', $this->movingIds)->values();

        // What each row allows; the toolbar buttons switch on / off from the ticked rows (no round trip).
        $ids = fn (callable $test) => $rows->filter($test)->map(fn (Application $a) => (string) $a->id)->values();
        $allowed = [
            'schedule' => $ids(fn (Application $a) => ! $a->stage->isFinal() && ! $a->interviews->contains('status', InterviewStatus::Scheduled)),
            'offer' => $ids(fn (Application $a) => ! $a->stage->isFinal() && (int) $a->stage->step() >= 2
                && ! $a->offers->contains(fn ($o) => ! $o->status()->isDead())),
            'move' => $ids(fn (Application $a) => $move->targetsFor($a) !== []),
        ];

        return view('hrm::livewire.recruitment.application-pipeline', [
            'posting' => $posting,
            'rows' => $rows,
            'counts' => $counts,
            'moving' => $moving,
            'allowed' => $allowed,
            'targets' => $moving->isEmpty() ? [] : collect($this->commonTargets($moving, $move))->mapWithKeys(fn (ApplicationStage $s) => [$s->value => $s->label()])->all(),
            'needsReason' => $this->to !== '' && ApplicationStage::tryFrom($this->to)?->needsReason(),
            'reasons' => RejectionReason::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
