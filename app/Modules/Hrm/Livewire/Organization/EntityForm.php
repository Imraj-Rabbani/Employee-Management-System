<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Core\Livewire\LookupForm;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\EntityType;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete entity (modal "entity-form"): e.g. Department "Finance" with its head.
 *
 *   - name unique within its type (two Departments called "Finance" are not allowed; a Branch may be)
 *   - head: any active employee (the approver for "Head of <type>" workflow steps)
 *   - parent: OPTIONAL entity of another type; when this entity has no head, approvals go to the parent's head.
 *     Rules in Entity::parentProblem() (other type, no loop).
 */
class EntityForm extends LookupForm
{
    protected function key(): string
    {
        return 'entity';
    }

    protected function model(): string
    {
        return Entity::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'code' => '', 'entity_type_id' => '', 'parent_id' => '', 'head_employee_id' => '', 'is_active' => true];
    }

    protected function fieldRules(): array
    {
        $typeId = $this->form['entity_type_id'] ?? null;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique(Entity::class, 'name')
                ->where('entity_type_id', $typeId === '' ? 0 : $typeId)->ignore($this->recordId)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique(Entity::class, 'code')->ignore($this->recordId)],
            'entity_type_id' => ['required', Rule::exists(EntityType::class, 'id')],
            'parent_id' => ['nullable', Rule::exists(Entity::class, 'id')],
            'head_employee_id' => ['nullable', Rule::exists(Employee::class, 'id')],
            'is_active' => ['boolean'],
        ];
    }

    protected function labels(): array
    {
        return ['entity_type_id' => __('type'), 'parent_id' => __('parent'), 'head_employee_id' => __('head')];
    }

    protected function check(array $values): bool
    {
        $entity = $this->recordId ? Entity::findOrFail($this->recordId) : new Entity;
        $entity->entity_type_id = (int) $values['entity_type_id'];

        $problem = $entity->parentProblem($values['parent_id'] ? Entity::find($values['parent_id']) : null);
        if ($problem !== null) {
            $this->addError('form.parent_id', $problem);

            return false;
        }

        return true;
    }

    protected function viewData(): array
    {
        $typeId = (int) ($this->form['entity_type_id'] ?? 0);
        $keepHead = array_filter([(int) ($this->form['head_employee_id'] ?? 0)]);

        return [
            'types' => EntityType::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $typeId))->ordered()->pluck('name', 'id')->all(),
            // Parents: entities of the OTHER types only (and never this one).
            'parents' => array_diff_key(
                Entity::options(Entity::where('entity_type_id', '!=', $typeId)->pluck('id')->map(fn ($id) => (int) $id)->all()),
                [$this->recordId ?? 0 => true],
            ),
            // Heads: active employees (all of them: an HR admin sets up the structure), plus the current head.
            'people' => Employee::withoutGlobalScope(VisibleToCurrentUser::class)
                ->where(fn ($q) => $q->active()->orWhereIn('id', $keepHead))
                ->orderBy('first_name')->orderBy('last_name')
                ->get(['id', 'code', 'first_name', 'last_name'])
                ->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->name} ({$e->code})"])
                ->all(),
        ];
    }

    protected function view(): string
    {
        return 'hrm::livewire.organization.entity-form';
    }
}
