<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Modules\Hrm\Models\OrgUnit;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * HRM → Organization → Org units, tree view. [ORG-01]
 *
 * - The whole tree is built from ONE query (units are grouped by parent in PHP).
 * - Expand / collapse runs in the browser only (Alpine), and is remembered ($persist).
 * - Drag a unit onto another to move it there, or onto "Top level". move() checks the same
 *   rules as the edit form (OrgUnit::placementProblem) and answers with a toast.
 *
 * Permission: the page route needs hrm.organization.manage; Livewire re-checks it on every call.
 */
class OrgUnitTree extends Component
{
    public function move(int $unitId, ?int $parentId): void
    {
        $unit = OrgUnit::with('type.parentType')->findOrFail($unitId);
        $parent = $parentId === null ? null : OrgUnit::findOrFail($parentId);

        if ($unit->parent_id === $parent?->id) {
            return;   // dropped where it already is
        }

        if (($problem = $unit->placementProblem($parent)) !== null) {
            $this->dispatch('toast', message: $problem, variant: 'danger');

            return;
        }

        $unit->update(['parent_id' => $parent?->id]);

        $this->dispatch('toast', variant: 'success', message: $parent
            ? __(':name moved under :parent.', ['name' => $unit->name, 'parent' => $parent->name])
            : __(':name is now a top-level unit.', ['name' => $unit->name]));
    }

    #[On('org-unit-saved')]
    public function refreshTree(): void
    {
        // Re-renders the tree after the edit form saved or deleted a unit.
    }

    public function render(): View
    {
        $units = OrgUnit::with('type')->withCount('positions')->orderBy('name')->get();
        $byParent = $units->groupBy(fn (OrgUnit $u) => $u->parent_id ?? 0)->all();   // [parent id => its children]

        return view('hrm::livewire.organization.org-unit-tree', [
            'roots' => $this->branch($byParent, 0),
            'parentIds' => $units->pluck('parent_id')->filter()->unique()->values()->all(),   // for "Collapse all"
            'total' => $units->count(),
        ]);
    }

    /**
     * Nested nodes: [['unit' => OrgUnit, 'children' => [...]], ...]. Recursion: a node's children
     * are built by calling this method again for that node's id.
     *
     * @param  array<int|string, Collection<int, OrgUnit>>  $byParent
     * @return list<array{unit: OrgUnit, children: list<mixed>}>
     */
    private function branch(array $byParent, int $parentId, int $depth = 0): array
    {
        if ($depth > 50) {
            return [];   // safety net against looping data imported from elsewhere
        }

        return ($byParent[$parentId] ?? new Collection)
            ->map(fn (OrgUnit $unit) => ['unit' => $unit, 'children' => $this->branch($byParent, $unit->id, $depth + 1)])
            ->values()
            ->all();
    }
}
