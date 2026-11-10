<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Models\Applicant;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Base for an applicant's list-of-rows cards (education, work history): the rows plus an add / edit /
 * delete modal. The applicant version of EmployeeRecords, simpler: no files, no policy (the page route
 * needs hrm.recruitment.manage, re-checked by Livewire on every click). [REC-03]
 *
 * A child class declares relation() ('educations' → modal "applicant-educations-form"), defaults(),
 * fieldRules(), view(); optional labels() and check($values).
 * Rows are always looked up THROUGH the applicant, so another applicant's row id gives 404.
 */
abstract class ApplicantRecords extends Component
{
    #[Locked]
    public int $applicantId;

    #[Locked]
    public ?int $recordId = null;

    /** @var array<string, mixed> */
    public array $form = [];

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

    /** @param  array<string, mixed>  $values */
    protected function check(array $values): bool
    {
        return true;
    }

    public function add(): void
    {
        $this->recordId = null;
        $this->form = $this->defaults();
        $this->resetValidation();
        Flux::modal($this->modalName())->show();
    }

    public function edit(int $id): void
    {
        $record = $this->records()->findOrFail($id);

        $this->recordId = $record->getKey();
        $this->form = [];
        foreach ($this->defaults() as $field => $default) {
            $value = $record->getAttribute($field);
            $this->form[$field] = match (true) {
                $value instanceof Carbon => $value->toDateString(),
                $value === null => '',
                default => $value,
            };
        }
        $this->resetValidation();
        Flux::modal($this->modalName())->show();
    }

    public function save(): void
    {
        $this->validate(
            collect($this->fieldRules())->mapWithKeys(fn ($r, $f) => ["form.{$f}" => $r])->all(),
            [],
            collect($this->labels())->mapWithKeys(fn ($l, $f) => ["form.{$f}" => $l])->all(),
        );

        $values = array_map(fn ($v) => $v === '' ? null : $v, array_intersect_key($this->form, $this->defaults()));
        if (! $this->check($values)) {
            return;
        }

        if ($this->recordId) {
            $this->records()->findOrFail($this->recordId)->update($values);
        } else {
            $this->records()->create($values);
        }

        $this->finish(__('Saved.'));
    }

    public function delete(): void
    {
        $this->records()->findOrFail($this->recordId)->delete();
        $this->finish(__('Deleted.'));
    }

    /** @return HasMany<TenantModel, Applicant> */
    protected function records(): HasMany
    {
        return Applicant::findOrFail($this->applicantId)->{$this->relation()}();
    }

    protected function modalName(): string
    {
        return 'applicant-'.Str::kebab($this->relation()).'-form';
    }

    private function finish(string $message): void
    {
        Flux::modal($this->modalName())->close();
        $this->recordId = null;
        $this->dispatch('toast', message: $message, variant: 'success');
    }

    public function render(): View
    {
        $rows = $this->records()->get();

        return view($this->view(), [
            'rows' => $rows,
            'modal' => $this->modalName(),
            'record' => $this->recordId ? $rows->firstWhere('id', $this->recordId) : null,
        ]);
    }
}
