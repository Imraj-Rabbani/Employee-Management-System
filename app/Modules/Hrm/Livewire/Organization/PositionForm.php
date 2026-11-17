<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Core\Livewire\LookupForm;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\Position;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete position (modal "position-form").
 * "Reports to" may not create a loop (A → B → A).
 */
class PositionForm extends LookupForm
{
    protected function key(): string
    {
        return 'position';
    }

    protected function model(): string
    {
        return Position::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => '', 'code' => '', 'entity_id' => '', 'grade_id' => '', 'parent_id' => '',
            'effective_from' => '', 'is_vacant' => true, 'is_active' => true,
        ];
    }

    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique(Position::class, 'code')->ignore($this->recordId)],
            'entity_id' => ['required', Rule::exists(Entity::class, 'id')],
            'grade_id' => ['nullable', Rule::exists(Grade::class, 'id')],
            'parent_id' => ['nullable', Rule::exists(Position::class, 'id')],
            'effective_from' => ['nullable', 'date'],
            'is_vacant' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    protected function labels(): array
    {
        return ['entity_id' => __('entity'), 'grade_id' => __('grade'), 'parent_id' => __('reports to')];
    }

    protected function check(array $values): bool
    {
        if ($this->recordId && $values['parent_id']
            && Position::findOrFail($this->recordId)->wouldLoopUnder((int) $values['parent_id'])) {
            $this->addError('form.parent_id', __('This would make a loop: the position would (indirectly) report to itself.'));

            return false;
        }

        return true;
    }

    protected function viewData(): array
    {
        $units = Entity::options();

        return [
            'units' => $units,
            'grades' => Grade::where('is_active', true)->ordered()->pluck('name', 'id')->all(),
            'bosses' => Position::whereKeyNot($this->recordId ?? 0)->orderBy('name')->get()
                ->mapWithKeys(fn (Position $p) => [$p->id => $p->name.' · '.($units[$p->entity_id] ?? '')])->all(),
        ];
    }

    protected function view(): string
    {
        return 'hrm::livewire.organization.position-form';
    }
}
