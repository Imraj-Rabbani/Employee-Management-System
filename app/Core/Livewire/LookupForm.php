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
 *     model()        Branch::class
 *     defaults()     the fields and their values for a new record: ['name' => '', 'is_active' => true]
 *     fieldRules()   validation per field: ['name' => ['required', ...]]
 *     view()         the Blade view with the inputs (wire:model="form.name")
 *
 * Optional: labels() for nicer error messages, viewData() for dropdown options,
 * check($values) for rules that need the database (add an error and return false).
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

    /**
     * Livewire asks this which browser events to listen to (a method, because the names depend on key()).
     *
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        return ["create-{$this->key()}" => 'create', "edit-{$this->key()}" => 'edit'];
    }

    public function create(): void
    {
        $this->recordId = null;
        $this->form = $this->defaults();
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
                $value === null => is_bool($default) ? $default : '',
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

        $record = $this->model()::updateOrCreate(['id' => $this->recordId], $values);

        $this->finish(__(':name saved.', ['name' => $this->title($record)]));
    }

    public function delete(): void
    {
        $record = $this->model()::findOrFail($this->recordId);

        try {
            // In a transaction: the delete and its activity-log entry are saved together, or not at all.
            $record->getConnection()->transaction(fn () => $record->delete());
        } catch (QueryException $e) {
            // 23000 = a foreign key refused it: other rows still point at this one.
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $this->addError('form.'.array_key_first($this->defaults()), __('This record is still in use and cannot be deleted. Make it inactive instead.'));

            return;
        }

        $this->finish(__(':name deleted.', ['name' => $this->title($record)]));
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
