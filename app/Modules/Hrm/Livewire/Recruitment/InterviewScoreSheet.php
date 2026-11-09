<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Models\User;
use App\Modules\Hrm\Enums\InterviewRecommendation;
use App\Modules\Hrm\Enums\InterviewStatus;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\InterviewFactor;
use App\Modules\Hrm\Models\InterviewPanelist;
use App\Modules\Hrm\Services\InterviewScoring;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * One panelist's score sheet (modal "interview-score-sheet"): a score 1–5 per interview factor with an
 * optional note, an overall recommendation and a comment. [REC-05]
 *
 *     $dispatch('open-score-sheet', { panelistId: 7 })
 *
 * Who may open it: the interviewer THEMSELF (their login is linked to the panelist's employee record:
 * Self Service → My Interviews), or HR (hrm.recruitment.manage + the posting's org unit) entering it on
 * their behalf, e.g. from a paper sheet. Anyone else: 403.
 *
 *   Save draft  keeps the scores, still editable
 *   Send        checks every REQUIRED factor is scored + a recommendation is chosen, then locks it
 *               (submitted_at). Only sent sheets count in the results.
 * Editable only while the interview is planned (not completed / cancelled) and the sheet is not sent.
 * Each score stores the factor's weight of that moment (InterviewScoring keeps old results stable).
 */
class InterviewScoreSheet extends Component
{
    #[Locked]
    public ?int $panelistId = null;

    /** @var array<int|string, int|string> factor id => score ('' = not scored) */
    public array $scores = [];

    /** @var array<int|string, string> factor id => note */
    public array $notes = [];

    public string $recommendation = '';

    public string $comment = '';

    /** Can this login open this panelist's sheet? Used here and by My Interviews. */
    public static function allows(User $user, InterviewPanelist $panelist): bool
    {
        if ($panelist->employee !== null && $panelist->employee->user_id === $user->id) {
            return true;
        }

        return $user->can('hrm.recruitment.manage')
            && Application::query()->visibleTo($user)->whereKey($panelist->interview->application_id)->exists();
    }

    #[On('open-score-sheet')]
    public function open(int $panelistId): void
    {
        $panelist = $this->panelist($panelistId);

        $this->panelistId = $panelist->id;
        $this->scores = [];
        $this->notes = [];
        foreach ($panelist->scores as $score) {
            $this->scores[$score->interview_factor_id] = $score->score;
            $this->notes[$score->interview_factor_id] = $score->note ?? '';
        }
        $this->recommendation = $panelist->recommendation ?? '';
        $this->comment = $panelist->comment ?? '';
        $this->resetValidation();

        Flux::modal('interview-score-sheet')->show();
    }

    public function saveDraft(): void
    {
        $this->store(send: false);
    }

    public function send(): void
    {
        $this->store(send: true);
    }

    private function store(bool $send): void
    {
        $panelist = $this->panelist((int) $this->panelistId);
        if (! $this->isEditable($panelist)) {
            $this->addError('scores', __('This score sheet can no longer be changed.'));

            return;
        }

        $factors = $this->factors($panelist);
        $rules = [
            'recommendation' => [$send ? 'required' : 'nullable', Rule::enum(InterviewRecommendation::class)],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
        foreach ($factors as $factor) {
            $rules["scores.{$factor->id}"] = [$send && $factor->is_required ? 'required' : 'nullable', 'integer', 'between:1,'.InterviewScoring::MAX];
            $rules["notes.{$factor->id}"] = ['nullable', 'string', 'max:500'];
        }
        $this->validate($rules, [
            'scores.*.required' => __('This factor must be scored.'),
        ], ['recommendation' => __('recommendation')]);

        $given = $factors->filter(fn (InterviewFactor $f) => ($this->scores[$f->id] ?? '') !== '');
        if ($send && $given->isEmpty()) {
            $this->addError('scores', __('Score at least one factor before sending.'));

            return;
        }

        DB::connection('tenant')->transaction(function () use ($panelist, $factors, $given, $send) {
            foreach ($factors as $factor) {
                if ($given->contains('id', $factor->id)) {
                    $panelist->scores()->updateOrCreate(['interview_factor_id' => $factor->id], [
                        'score' => (int) $this->scores[$factor->id],
                        'weight' => $factor->weight,   // the weight of this moment
                        'note' => trim($this->notes[$factor->id] ?? '') ?: null,
                    ]);
                } else {
                    $panelist->scores()->where('interview_factor_id', $factor->id)->delete();
                }
            }

            $panelist->update([
                'recommendation' => $this->recommendation ?: null,
                'comment' => trim($this->comment) ?: null,
            ]);
            if ($send) {
                $panelist->forceFill(['submitted_at' => now()])->save();
            }
        });

        if ($send) {
            Flux::modal('interview-score-sheet')->close();
        }
        $this->dispatch('interview-saved');
        $this->dispatch('toast', message: $send ? __('Scores sent. Thank you.') : __('Draft saved.'), variant: 'success');
    }

    private function isEditable(InterviewPanelist $panelist): bool
    {
        return ! $panelist->isSubmitted() && $panelist->interview->status === InterviewStatus::Scheduled;
    }

    /**
     * The active factors in order, plus any (now inactive) factor this sheet already has a score for.
     *
     * @return Collection<int, InterviewFactor>
     */
    private function factors(InterviewPanelist $panelist): Collection
    {
        $scored = $panelist->scores->pluck('interview_factor_id')->all();

        return InterviewFactor::query()->ordered()
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $scored))
            ->get();
    }

    /** 404 when it does not exist, 403 when this login may not open it. */
    private function panelist(int $id): InterviewPanelist
    {
        $panelist = InterviewPanelist::with(['employee', 'scores', 'interview.application.applicant', 'interview.application.jobPosting'])->findOrFail($id);
        abort_unless(self::allows(Auth::user(), $panelist), 403);

        return $panelist;
    }

    public function render(): View
    {
        $panelist = $this->panelistId ? $this->panelist($this->panelistId) : null;

        return view('hrm::livewire.recruitment.interview-score-sheet', [
            'panelist' => $panelist,
            'factors' => $panelist ? $this->factors($panelist) : collect(),
            'editable' => $panelist && $this->isEditable($panelist),
            'onBehalf' => $panelist && $panelist->employee?->user_id !== Auth::id(),
            'result' => $panelist ? app(InterviewScoring::class)->panelist($panelist) : null,
            'recommendations' => InterviewRecommendation::options(),
            'max' => InterviewScoring::MAX,
        ]);
    }
}
