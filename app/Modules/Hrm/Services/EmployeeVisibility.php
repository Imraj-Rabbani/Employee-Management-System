<?php

namespace App\Modules\Hrm\Services;

use App\Models\User;
use App\Modules\Core\Services\Permissions;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\EntityType;
use Illuminate\Validation\Rule;

/**
 * Which employees may this login see? Applies the role's entity scope (Role::$entity_id, legacy profiles.entity).
 *
 *     app(EmployeeVisibility::class)->entityIdsFor($user)   null = everyone, [3] = only employees currently in entity 3
 *     app(EmployeeVisibility::class)->canSee($user, $emp)   one employee
 *
 * Rules:
 *   - Administrator, or a role without an entity → every employee
 *   - a role limited to Department "Finance" → employees whose current Department is Finance
 *     (entities are flat: a parent entity does NOT include its children)
 *   - no role in this company → nobody
 *   - everyone always sees their OWN employee record (ESS)
 *
 * Used by the VisibleToCurrentUser global scope (every Employee query) and by EmployeePolicy.
 */
class EmployeeVisibility
{
    public function __construct(private Permissions $permissions) {}

    /** @return list<int>|null null = no limit */
    public function entityIdsFor(User $user): ?array
    {
        $limit = $this->permissions->entityLimit($user);

        return match (true) {
            $limit === null => null,
            $limit === false => [],
            default => [$limit],
        };
    }

    /**
     * Entities this user may pick in filters and forms: [id => "Finance (Department)"].
     * A user limited to Department Finance gets Finance and every entity of the OTHER types
     * (they may change someone's Branch, but moving them to another Department would hide them).
     *
     * @return array<int, string>
     */
    public function entityOptionsFor(?User $user): array
    {
        $visible = $user ? $this->entityIdsFor($user) : null;
        if ($visible === null) {
            return Entity::options();
        }

        $limitTypes = Entity::whereIn('id', $visible)->pluck('entity_type_id')->all();

        return Entity::options(
            Entity::where(fn ($q) => $q->whereIn('id', $visible)->orWhereNotIn('entity_type_id', $limitTypes))
                ->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    /**
     * Entities a DOCUMENT may be made for (a roster, a manpower request, a hire): [id => "Finance (Department)"].
     * Strict: a user limited to Finance gets only Finance, because rosters and requests are listed by their
     * entity (Roster::visibleTo, ManpowerRequest::visibleTo) and one made for another entity would vanish.
     *
     * @return array<int, string>
     */
    public function documentEntityOptionsFor(?User $user): array
    {
        $visible = $user ? $this->entityIdsFor($user) : null;

        return Entity::options($visible);
    }

    /**
     * For forms with ONE dropdown PER TYPE (new employee, hire): the active types and the entities this
     * user may pick in each. A type is required when the user is limited to an entity of that type,
     * otherwise the new employee would be invisible to them right after saving.
     *
     *     [typeId => ['name' => 'Department', 'options' => [3 => 'Finance', ...], 'required' => true], ...]
     *
     * @return array<int, array{name: string, options: array<int, string>, required: bool}>
     */
    public function entityChoicesFor(?User $user): array
    {
        $visible = ($user ? $this->entityIdsFor($user) : null) ?? [];   // [] here = no limit (no limited types)
        $limitTypes = Entity::whereIn('id', $visible)->pluck('entity_type_id')->map(fn ($id) => (int) $id)->all();
        $byType = Entity::optionsByType();

        return EntityType::where('is_active', true)->ordered()->get()
            ->mapWithKeys(function (EntityType $type) use ($visible, $limitTypes, $byType) {
                $options = $byType[$type->id] ?? [];
                $required = in_array($type->id, $limitTypes, true);
                if ($required) {
                    $options = array_intersect_key($options, array_flip($visible));
                }

                return [$type->id => ['name' => $type->name, 'options' => $options, 'required' => $required]];
            })
            ->all();
    }

    /**
     * Validation rules for those dropdowns, keyed "{$prefix}.{typeId}", e.g. "form.entities.3".
     *
     * @param  array<int, array{name: string, options: array<int, string>, required: bool}>  $choices
     * @return array<string, list<mixed>>
     */
    public static function entityChoiceRules(array $choices, string $prefix): array
    {
        $rules = [];
        foreach ($choices as $typeId => $choice) {
            $rules["{$prefix}.{$typeId}"] = [$choice['required'] ? 'required' : 'nullable', Rule::in(array_keys($choice['options']))];
        }

        return $rules;
    }

    public function canSee(User $user, Employee $employee): bool
    {
        if ($employee->user_id !== null && $employee->user_id === $user->id) {
            return true;
        }

        $ids = $this->entityIdsFor($user);

        return $ids === null || array_intersect($employee->entityIds(), $ids) !== [];
    }
}
