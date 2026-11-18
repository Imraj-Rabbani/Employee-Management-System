<?php

namespace App\Core\Livewire;

use App\Core\Models\TenantModel;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Base class for the many small New / Edit / Delete modal forms of setup lists
 * (branches, grades, designations ...). A child class only declares WHAT differs:
 *
 *     key()          'branch' → events "create-branch" / "edit-branch" / "branch-saved", modal "branch-form"
 *     model()        Designation::class
 *     defaults()     the fields and their values for a new record: ['name' => '', 'is_active' => true]
 *     fieldRules()   validation per field: ['name' => ['required', ...]]
 *     view()         the Blade view with the inputs (wire:model="form.name")
 *
 * Optional: labels() for nicer error messages, viewData() for dropdown options,
 * check($values) for rules that need the database (add an error and return false),
 * saved($record) for extra saving after the record itself (same transaction),
 * deleteProblem($record) for a reason a record may not be deleted, deleted($record) for work after a delete.
 *
 * Deleting: from the edit modal (delete(), one record) or from the list's toolbar (deleteMany(), the ticked
 * rows: the table dispatches "delete-{key}" with { ids: [...] }). Both use deleteRecord(), so the rules are the same.
 *
 * All values live in ONE array property, $form, so this class can fill and save any model.
 */
abstract class LookupForm extends Component
{
    /** null = creating a new record */
    #[Locked]
    public ?int $recordId = null;

    /** @var array<string, mixed> field => value */
    public array $form = [];

    abstract protected function key(): string;

    /** @return class-string<TenantModel> */
    abstract protected function model(): string;

    /** @return array<string, mixed> */
    abstract protected function defaults(): array;

    /** @return array<string, mixed> */
    abstract protected function fieldRules(): array;

    abstract protected function view(): string;

    /** @return array<string, string> field => label used in validation messages */
    protected function labels(): array
    {
        return [];
    }

    /** @return array<string, mixed> extra variables for the view, e.g. dropdown options */
    protected function viewData(): array
    {
        return [];
    }

    /** @param  array<string, mixed>  $values */
    protected function check(array $values): bool
    {
        return true;
    }

    /** After the record is saved, in the same transaction: e.g. sync a many-to-many list. */
    protected function saved(TenantModel $record): void {}

    /** Why this record may not be deleted (shown to the user), or null. Foreign keys are checked anyway. */
    protected function deleteProblem(TenantModel $record): ?string
    {
        return null;
    }

    /** After a record was deleted (outside the transaction): e.g. reprocess attendance. */
    protected function deleted(TenantModel $record): void {}

    /**
     * Livewire asks this which browser events to listen to (a method, because the names depend on key()).
     *
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        return ["create-{$this->key()}" => 'create', "edit-{$this->key()}" => 'edit', "delete-{$this->key()}" => 'deleteMany'];
    }

    /**
     * @param  array<string, mixed>  $preset  start values, e.g. $dispatch('create-entity', { preset: { parent_id: 5 } })
     */
    public function create(array $preset = []): void
    {
        $this->recordId = null;
        $this->form = array_replace($this->defaults(), array_intersect_key($preset, $this->defaults()));
        $this->resetValidation();

        Flux::modal($this->modalName())->show();
    }

    public function edit(int $id): void
    {
        $record = $this->model()::findOrFail($id);

        $this->recordId = $record->getKey();
        $this->form = [];
        foreach ($this->defaults() as $field => $default) {
            $value = $record->getAttribute($field);
            $this->form[$field] = match (true) {
                $value instanceof Carbon => $value->toDateString(),   // <input type="date"> wants 2026-11-07
                $value instanceof \BackedEnum => $value->value,        // <select> wants the number, e.g. 5 for Friday
                $value === null => is_bool($default) ? $default : '',
                $value instanceof \Stringable => (string) $value,  // a Money cast → "2500.50"
                default => $value,
            };
        }
        $this->resetValidation();

        Flux::modal($this->modalName())->show();
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return collect($this->fieldRules())->mapWithKeys(fn ($rules, $field) => ["form.{$field}" => $rules])->all();
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return collect($this->labels())->mapWithKeys(fn ($label, $field) => ["form.{$field}" => $label])->all();
    }

    public function save(): void
    {
        $this->validate();

        // Empty inputs are saved as NULL (e.g. no code, no parent).
        $values = array_map(fn ($v) => $v === '' ? null : $v, array_intersect_key($this->form, $this->defaults()));

        if (! $this->check($values)) {
            return;
        }

        // In a transaction: the record and whatever saved() adds (e.g. pivot rows) are saved together.
        $model = $this->model();
        $record = (new $model)->getConnection()->transaction(function () use ($model, $values) {
            $record = $model::updateOrCreate(['id' => $this->recordId], $values);
            $this->saved($record);

            return $record;
        });

        $this->finish(__(':name saved.', ['name' => $this->title($record)]));
    }

    /** Delete from the edit modal: the record being edited. A refusal is shown under the first field. */
    public function delete(): void
    {
        $record = $this->model()::findOrFail($this->recordId);

        if ($problem = $this->deleteRecord($record)) {
            $this->addError('form.'.array_key_first($this->defaults()), $problem);

            return;
        }

        $this->finish(__(':name deleted.', ['name' => $this->title($record)]));
    }

    /**
     * Delete from the list's toolbar: every ticked row that may go. The others stay, and one toast says
     * what happened, e.g. "2 deleted. Grade 1: still in use …".
     *
     * @param  list<int|string>  $ids
     */
    public function deleteMany(array $ids): void
    {
        $deleted = 0;
        $problems = [];

        foreach ($this->model()::query()->whereKey($ids)->get() as $record) {
            if ($problem = $this->deleteRecord($record)) {
                $problems[] = $this->title($record).': '.$problem;
            } else {
                $deleted++;
            }
        }

        $this->dispatch($this->key().'-saved');
        $this->dispatch('toast',
            message: implode(' ', array_filter([$deleted > 0 ? trans_choice(':count deleted.|:count deleted.', $deleted) : null, ...$problems])),
            variant: $problems === [] ? 'success' : ($deleted > 0 ? 'warning' : 'error'),
        );
    }

    /** Deletes one record. Returns why it could not be deleted, or null when it was. */
    protected function deleteRecord(TenantModel $record): ?string
    {
        if ($problem = $this->deleteProblem($record)) {
            return $problem;
        }

        try {
            // In a transaction: the delete and its activity-log entry are saved together, or not at all.
            $record->getConnection()->transaction(fn () => $record->delete());
        } catch (QueryException $e) {
            // 23000 = a foreign key refused it: other rows still point at this one.
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            return __('This record is still in use and cannot be deleted. Make it inactive instead.');
        }

        $this->deleted($record);

        return null;
    }

    /** Shown in the toast. */
    protected function title(TenantModel $record): string
    {
        return (string) ($record->getAttribute('name') ?? $record->getAttribute('code') ?? '#'.$record->getKey());
    }

    protected function modalName(): string
    {
        return $this->key().'-form';
    }

    private function finish(string $message): void
    {
        Flux::modal($this->modalName())->close();
        $this->dispatch($this->key().'-saved');
        $this->dispatch('toast', message: $message, variant: 'success');
    }

    public function render(): View
    {
        return view($this->view(), [
            'modal' => $this->modalName(),
            'record' => $this->recordId ? $this->model()::find($this->recordId) : null,
            ...$this->viewData(),
        ]);
    }
}
