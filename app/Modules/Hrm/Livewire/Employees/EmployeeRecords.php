<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Core\Models\TenantModel;
use App\Core\Support\TenantUploads;
use App\Modules\Hrm\Models\Employee;
use BackedEnum;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Base for the profile's list-of-records sections (family, nominees, education, documents, bank ...):
 * a card with the rows, plus a modal to add / edit / delete one. [EMP-02, EMP-03]
 *
 * A child class declares:
 *     relation()     'nominees' → $employee->nominees(); events "add-employee-nominees", modal "employee-nominees-form"
 *     defaults()     the form fields and their values for a new row
 *     fieldRules()   validation per field
 *     view()         the Blade view: the list card + <x-form.lookup-modal> with the inputs
 * Optional: labels(), viewData(), check($values), saved($employee, $record), deleted($employee),
 *           toForm($record) / fromForm($values) when the form differs from the columns (e.g. JSON),
 *           attachmentFolder() to allow ONE file per row (needs columns attachment_path + attachment_name):
 *           the view adds <x-form.file wire:model="upload" />, the base stores / replaces / deletes it.
 *
 * Security: reading needs EmployeePolicy::view, every change EmployeePolicy::update, and a row is always
 * looked up THROUGH the employee ($employee->nominees()->findOrFail($id)), so an id of another employee's
 * row sent from the browser gives 404.
 */
abstract class EmployeeRecords extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $employeeId;

    /** null = adding a new row */
    #[Locked]
    public ?int $recordId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** The file chosen in the form (only when attachmentFolder() is set). */
    public ?TemporaryUploadedFile $upload = null;

    abstract protected function relation(): string;

    /** @return array<string, mixed> */
    abstract protected function defaults(): array;

    /** @return array<string, mixed> */
    abstract protected function fieldRules(): array;

    abstract protected function view(): string;

    /** @return array<string, string> */
    protected function labels(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    protected function viewData(): array
    {
        return [];
    }

    /** Runs after a row is added or changed, e.g. to keep "only one primary" true. */
    protected function saved(Employee $employee, TenantModel $record): void {}

    /** Runs after a row is deleted. */
    protected function deleted(Employee $employee): void {}

    /** Folder on the tenant disk for attachments, e.g. 'employees/educations'. null = no attachments. */
    protected function attachmentFolder(): ?string
    {
        return null;
    }

    /** @return list<string> */
    protected function attachmentRules(): array
    {
        return ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,docx', 'max:5120'];
    }

    /** @param  array<string, mixed>  $values */
    protected function check(array $values): bool
    {
        return true;
    }

    /**
     * A row's columns → form values. Default: each default() field from the column of the same name.
     *
     * @return array<string, mixed>
     */
    protected function toForm(TenantModel $record): array
    {
        $form = [];
        foreach ($this->defaults() as $field => $default) {
            $value = $record->getAttribute($field);
            $form[$field] = match (true) {
                $value instanceof Carbon => $value->toDateString(),
                $value instanceof BackedEnum => $value->value,
                $value === null => is_bool($default) ? $default : '',
                default => $value,
            };
        }

        return $form;
    }

    /**
     * Validated form values → columns. Default: unchanged.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function fromForm(array $values): array
    {
        return $values;
    }

    public function mount(Employee $employee): void
    {
        $this->employeeId = $employee->id;
    }

    public function add(): void
    {
        $this->employee(update: true);
        $this->recordId = null;
        $this->form = $this->defaults();
        $this->reset('upload');
        $this->resetValidation();

        Flux::modal($this->modalName())->show();
    }

    public function edit(int $id): void
    {
        $record = $this->records($this->employee(update: true))->findOrFail($id);

        $this->recordId = $record->getKey();
        $this->form = $this->toForm($record);
        $this->reset('upload');
        $this->resetValidation();

        Flux::modal($this->modalName())->show();
    }

    public function save(): void
    {
        $employee = $this->employee(update: true);
        $this->validate();

        $values = array_map(fn ($v) => $v === '' ? null : $v, array_intersect_key($this->form, $this->defaults()));
        if (! $this->check($values)) {
            return;
        }
        $values = $this->fromForm($values);

        $record = $this->recordId ? $this->records($employee)->findOrFail($this->recordId) : null;
        $oldFile = $record?->getAttribute('attachment_path');

        if ($this->upload && $this->attachmentFolder()) {
            $values['attachment_path'] = app(TenantUploads::class)->store($this->upload, $this->attachmentFolder());
            $values['attachment_name'] = $this->upload->getClientOriginalName();
        }

        if ($record) {
            $record->update($values);
        } else {
            $record = $this->records($employee)->create($values);
        }
        $this->saved($employee, $record);

        if (isset($values['attachment_path'])) {
            app(TenantUploads::class)->delete($oldFile);   // replaced: the old file goes
        }

        $this->finish(__('Saved.'));
    }

    public function delete(): void
    {
        $employee = $this->employee(update: true);
        $record = $this->records($employee)->findOrFail($this->recordId);
        $file = $record->getAttribute('attachment_path');

        $record->delete();
        app(TenantUploads::class)->delete($file);
        $this->deleted($employee);

        $this->finish(__('Deleted.'));
    }

    /** "Remove file" in the form: deletes only the attachment, the row stays. */
    public function removeAttachment(): void
    {
        $record = $this->records($this->employee(update: true))->findOrFail($this->recordId);

        app(TenantUploads::class)->delete($record->getAttribute('attachment_path'));
        $record->update(['attachment_path' => null, 'attachment_name' => null]);
    }

    /** @return HasMany<TenantModel, Employee> */
    protected function records(Employee $employee): HasMany
    {
        return $employee->{$this->relation()}();
    }

    /** Loads the employee (404 outside your scope) and checks the policy (403). */
    protected function employee(bool $update = false): Employee
    {
        $employee = Employee::findOrFail($this->employeeId);
        Gate::authorize($update ? 'update' : 'view', $employee);

        return $employee;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $rules = collect($this->fieldRules())->mapWithKeys(fn ($rules, $field) => ["form.{$field}" => $rules])->all();

        return $this->attachmentFolder() ? [...$rules, 'upload' => $this->attachmentRules()] : $rules;
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            ...collect($this->labels())->mapWithKeys(fn ($label, $field) => ["form.{$field}" => $label])->all(),
            'upload' => __('attachment'),
        ];
    }

    protected function modalName(): string
    {
        return 'employee-'.Str::kebab($this->relation()).'-form';
    }

    private function finish(string $message): void
    {
        Flux::modal($this->modalName())->close();
        $this->recordId = null;
        $this->reset('upload');
        $this->dispatch('toast', message: $message, variant: 'success');
    }

    public function render(): View
    {
        $employee = $this->employee();

        /** @var Collection<int, TenantModel> $rows */
        $rows = $this->records($employee)->get();

        return view($this->view(), [
            'employee' => $employee,
            'rows' => $rows,
            'canUpdate' => Gate::allows('update', $employee),
            'modal' => $this->modalName(),
            'section' => Str::kebab($this->relation()),   // for route('hrm.employees.attachment', ...)
            'record' => $this->recordId ? $rows->firstWhere('id', $this->recordId) : null,
            ...$this->viewData(),
        ]);
    }
}
