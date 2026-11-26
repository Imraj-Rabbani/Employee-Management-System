<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Core\Livewire\LookupForm;
use App\Core\Models\TenantModel;
use App\Modules\Hrm\Models\InterviewQuestion;
use App\Modules\Hrm\Models\QuestionSet;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete question set (modal "question-set-form"), Recruitment → Config → Question sets.
 *
 *   name, description, active                     saved by LookupForm
 *   $items = [['question_id' => 3, 'weight' => 40], ...]   the questions in score-sheet order, saved by saved()
 *
 * Rules: at least one question, each once, the weights add up to exactly 100 %.
 * Once a stage uses the set, its questions and weights are locked (the stage's score sheets must stay
 * the same for everyone); the name, description and active switch can still change.
 */
class QuestionSetForm extends LookupForm
{
    /** @var list<array{question_id: int|string, weight: int|float|string}> */
    public array $items = [];

    /** The question picked in "Add question" ('' = none). */
    public int|string $addQuestionId = '';

    protected function key(): string
    {
        return 'question-set';
    }

    protected function model(): string
    {
        return QuestionSet::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'description' => '', 'is_active' => true];
    }

    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique(QuestionSet::class, 'name')->ignore($this->recordId)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }

    public function create(array $preset = []): void
    {
        $this->items = [];
        $this->addQuestionId = '';
        parent::create($preset);
    }

    public function edit(int $id): void
    {
        $set = QuestionSet::with('items')->findOrFail($id);
        $this->items = $set->items->map(fn ($item) => [
            'question_id' => $item->interview_question_id,
            'weight' => rtrim(rtrim((string) $item->weight, '0'), '.'),
        ])->all();
        $this->addQuestionId = '';
        parent::edit($id);
    }

    // ---- the question list ----------------------------------------------------------------

    public function updatedAddQuestionId(): void
    {
        $id = (int) $this->addQuestionId;
        if ($id > 0 && ! $this->locked() && ! in_array($id, array_map(fn ($i) => (int) $i['question_id'], $this->items), true)
            && InterviewQuestion::whereKey($id)->where('is_active', true)->exists()) {
            $this->items[] = ['question_id' => $id, 'weight' => 0];
        }
        $this->addQuestionId = '';
    }

    public function removeItem(int $index): void
    {
        if (! $this->locked()) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
    }

    /** Move a question up (-1) or down (+1) on the score sheet. */
    public function moveItem(int $index, int $step): void
    {
        $to = $index + $step;
        if (! $this->locked() && isset($this->items[$index], $this->items[$to])) {
            [$this->items[$index], $this->items[$to]] = [$this->items[$to], $this->items[$index]];
        }
    }

    /** "Split equally": 100 shared out, the rounding difference on the last question (e.g. 33.33, 33.33, 33.34). */
    public function splitEqually(): void
    {
        $count = count($this->items);
        if ($count === 0 || $this->locked()) {
            return;
        }
        $each = floor(10000 / $count) / 100;
        foreach ($this->items as $i => $item) {
            $this->items[$i]['weight'] = $i === $count - 1 ? round(100 - $each * ($count - 1), 2) : $each;
        }
    }

    // ---- saving --------------------------------------------------------------------------------

    protected function check(array $values): bool
    {
        if ($this->locked()) {
            return true;   // only name / description / active change; the questions stay as they are
        }

        $this->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.question_id' => ['required', 'integer', 'distinct', Rule::exists(InterviewQuestion::class, 'id')],
            'items.*.weight' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
        ], [
            'items.required' => __('Add at least one question.'),
            'items.min' => __('Add at least one question.'),
        ], ['items.*.weight' => __('weight')]);

        $total = round(array_sum(array_map(fn ($i) => (float) $i['weight'], $this->items)), 2);
        if ($total !== 100.0) {
            $this->addError('items', __('The weights add up to :total%. They must add up to 100%.', ['total' => $this->percent($total)]));

            return false;
        }

        return true;
    }

    /** @param  QuestionSet  $record */
    protected function saved(TenantModel $record): void
    {
        if ($this->locked()) {
            return;
        }

        $record->items()->delete();
        foreach (array_values($this->items) as $i => $item) {
            $record->items()->create(['interview_question_id' => (int) $item['question_id'], 'weight' => $item['weight'], 'sequence' => $i + 1]);
        }
    }

    protected function deleteProblem(TenantModel $record): ?string
    {
        return $record instanceof QuestionSet && $record->isUsed()
            ? __('An interview stage uses it. Make it inactive instead.')
            : null;
    }

    /** Editing a set a stage uses: its questions cannot change. */
    private function locked(): bool
    {
        return $this->recordId !== null && (bool) QuestionSet::find($this->recordId)?->isUsed();
    }

    private function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    protected function viewData(): array
    {
        $chosen = array_map(fn ($i) => (int) $i['question_id'], $this->items);
        $questions = InterviewQuestion::query()->ordered()->get()->keyBy('id');

        return [
            'locked' => $this->locked(),
            'questions' => $questions,
            'addable' => $questions->filter(fn (InterviewQuestion $q) => $q->is_active && ! in_array($q->id, $chosen, true)),
            'total' => $this->percent(round(array_sum(array_map(fn ($i) => is_numeric($i['weight']) ? (float) $i['weight'] : 0, $this->items)), 2)),
        ];
    }

    protected function view(): string
    {
        return 'hrm::livewire.recruitment.question-set-form';
    }
}
