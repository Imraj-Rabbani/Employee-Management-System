<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Core\Livewire\LookupForm;
use App\Modules\Hrm\Models\EntityType;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete entity type (modal "entity-type-form"), e.g. Department, Branch.
 * Everything except the fields comes from LookupForm. Delete is refused while entities of the type exist.
 */
class EntityTypeForm extends LookupForm
{
    protected function key(): string
    {
        return 'entity-type';
    }

    protected function model(): string
    {
        return EntityType::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'sequence' => 1, 'is_active' => true];
    }

    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique(EntityType::class, 'name')->ignore($this->recordId)],
            'sequence' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }

    protected function view(): string
    {
        return 'hrm::livewire.organization.entity-type-form';
    }
}
