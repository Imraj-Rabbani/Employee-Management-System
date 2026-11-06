<?php

namespace App\Modules\Workflow\Livewire;

use App\Modules\Workflow\Models\ApprovalType;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * New / Edit approval type (modal "approval-type-form"). [WF-01]
 * The key is what the code uses (Approvable::approvalType(), e.g. "leave"), so it cannot change after creation.
 */
class ApprovalTypeForm extends Component
{
    #[Locked]
    public ?int $typeId = null;

    public string $key = '';

    public string $name = '';

    public bool $isChained = true;

    public bool $isActive = true;

    #[On('create-approval-type')]
    public function create(): void
    {
        $this->reset();
        $this->resetValidation();
        Flux::modal('approval-type-form')->show();
    }

    #[On('edit-approval-type')]
    public function edit(int $id): void
    {
        $type = ApprovalType::findOrFail($id);

        $this->resetValidation();
        $this->typeId = $type->id;
        $this->key = $type->key;
        $this->name = $type->name;
        $this->isChained = $type->is_chained;
        $this->isActive = $type->is_active;

        Flux::modal('approval-type-form')->show();
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'key' => $this->typeId ? [] : ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique(ApprovalType::class, 'key')],
            'name' => ['required', 'string', 'max:100'],
            'isChained' => ['boolean'],
            'isActive' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return ['key.regex' => __('Use lower-case letters, digits and _ only, e.g. leave or payroll_run.')];
    }

    public function save(): void
    {
        $this->validate();

        $values = ['name' => $this->name, 'is_chained' => $this->isChained, 'is_active' => $this->isActive];

        $type = $this->typeId
            ? tap(ApprovalType::findOrFail($this->typeId))->update($values)
            : ApprovalType::create(['key' => $this->key, ...$values]);

        Flux::modal('approval-type-form')->close();
        $this->dispatch('approval-type-saved');
        $this->dispatch('toast', message: __(':name saved.', ['name' => $type->name]), variant: 'success');
    }

    public function render(): View
    {
        return view('workflow::livewire.approval-type-form');
    }
}
