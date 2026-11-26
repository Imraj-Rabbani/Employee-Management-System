<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Models\User;
use App\Modules\Hrm\Actions\SubmitScoreSheet;
use App\Modules\Hrm\Enums\InterviewRecommendation;
use App\Modules\Hrm\Enums\InterviewStatus;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\InterviewPanelist;
use App\Modules\Hrm\Models\InterviewScore;
use App\Modules\Hrm\Models\QuestionSetItem;
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
 * One panelist's score sheet (modal "interview-score-sheet"): a score 1–5 per question of the stage's
 * question set with an optional note, an overall recommendation and a comment.
 *
 *     $dispatch('open-score-sheet', { panelistId: 7 })
 *
 * Who may open it: the interviewer THEMSELF (their login is linked to the panelist's employee record:
 * Self Service → My Interviews), or HR (hrm.recruitment.manage + the posting's entity) entering it on
 * their behalf, e.g. from a paper sheet. Anyone else: 403.
 *
 *   Save draft  keeps the scores, still editable
 *   Send        checks every question of the set is scored + a recommendation is chosen, then locks it
 *               (submitted_at, Hrm\Actions\SubmitScoreSheet). Only sent sheets count in the results.
 *               The button is shown to HR entering a sheet on someone's behalf; interviewers save here and
 *               send from My Interviews, where they tick several sheets and send them at once.
 * Editable only while the interview is planned (not completed / cancelled) and the sheet is not sent.
 * Each score stores the question's weight in the set at that moment (InterviewScoring keeps old results stable).
 */
class InterviewScoreSheet extends Component
{
    #[Locked]
    public ?int $panelistId = null;

    /** @var array<int|string, int|string> question id => score ('' = not scored) */
    public array $scores = [];

    /** @var array<int|string, string> question id => note */
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
            $this->scores[$score->interview_question_id] = $score->score;
            $this->notes[$score->interview_question_id] = $score->note ?? '';
        }
        $this->recommendation = $panelist->recommendation ?? '';
        $this->comment = $panelist->comment ?? '';
        $this->resetValidation();

        Flux::modal('interview-score-sheet')->show();
    }

    /** @param  bool  $close  "Save and close" (the interviewer's button): close the window when it saved */
    public function saveDraft(bool $close = false): void
    {
        $this->store(send: false);

        if ($close && $this->getErrorBag()->isEmpty()) {
            Flux::modal('interview-score-sheet')->close();
        }
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
            'scores.*.required' => __('Score this question.'),
        ], ['recommendation' => __('recommendation')]);

        $given = $factors->filter(fn (object $f) => ($this->scores[$f->id] ?? '') !== '');
        if ($send && $given->isEmpty()) {
            $this->addError('scores', __('Score the questions before sending.'));

            return;
        }

        DB::connection('tenant')->transaction(function () use ($panelist, $factors, $given, $send) {
            foreach ($factors as $factor) {
                if ($given->contains('id', $factor->id)) {
                    $panelist->scores()->updateOrCreate(['interview_question_id' => $factor->id], [
                        'score' => (int) $this->scores[$factor->id],
                        'weight' => $factor->weight,   // the question's weight in the set, at this moment
                        'note' => trim($this->notes[$factor->id] ?? '') ?: null,
                    ]);
                } else {
                    $panelist->scores()->where('interview_question_id', $factor->id)->delete();
                }
            }

            $panelist->update([
                'recommendation' => $this->recommendation ?: null,
                'comment' => trim($this->comment) ?: null,
            ]);
            if ($send) {
                app(SubmitScoreSheet::class)->handle($panelist);   // the same rules as sending from My Interviews
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
     * The questions of the interview's stage (its question set), in order, each with its weight in that set.
     * Every question must be scored before sending. A question scored earlier that is not in the set any more
     * (old data) is still shown, with the weight it was scored with.
     *
     * @return Collection<int, object{id: int, name: string, description: ?string, weight: string, is_required: bool}>
     */
    private function factors(InterviewPanelist $panelist): Collection
    {
        $items = $panelist->interview->stage?->questionSet?->items()->with('question')->get() ?? collect();
        $questions = $items->map(fn (QuestionSetItem $item) => (object) [
            'id' => $item->interview_question_id, 'name' => $item->question->name, 'description' => $item->question->description,
            'weight' => (string) $item->weight, 'is_required' => true,
        ]);

        $extra = $panelist->scores->whereNotIn('interview_question_id', $questions->pluck('id'))->load('question')
            ->map(fn (InterviewScore $score) => (object) [
                'id' => $score->interview_question_id, 'name' => $score->question->name, 'description' => $score->question->description,
                'weight' => (string) $score->weight, 'is_required' => false,
            ]);

        return $questions->concat($extra)->values();
    }

    /** 404 when it does not exist, 403 when this login may not open it. */
    private function panelist(int $id): InterviewPanelist
    {
        $panelist = InterviewPanelist::with(['employee', 'scores', 'interview.stage', 'interview.application.applicant', 'interview.application.jobPosting'])->findOrFail($id);
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
