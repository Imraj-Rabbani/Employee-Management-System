<?php

namespace App\Modules\Hrm\Livewire;

use App\Modules\Hrm\Enums\EmployeeStatus;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Position;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Hrm\Services\EmployeeVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * HRM → Org Chart: who reports to whom (people) or the position tree (positions). [ORG-02]
 *
 * - ONE query per render; the tree is built in PHP (like the org unit tree, 4.3).
 * - Only OPENED branches are drawn ($open), so a company with thousands of people stays fast.
 * - People: only employees you may see (VisibleToCurrentUser), separated ones left out. Someone whose
 *   supervisor you cannot see is shown at the top.
 * - Positions: active positions in your org units; the holder's name only if you may see them.
 * - ?focus=12 opens the chain down to employee 12 and highlights them (profile → "Show in org chart").
 */
class OrgChart extends Component
{
    /** 'people' or 'positions' (in the URL, so it can be bookmarked). */
    #[Url]
    public string $view = 'people';

    /** Employee (people view) or position (positions view) to show and highlight. */
    #[Url]
    public ?int $focus = null;

    /** @var list<int> ids of the nodes whose children are shown */
    public array $open = [];

    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Employee::class);
        $this->view = in_array($this->view, ['people', 'positions'], true) ? $this->view : 'people';
        $this->reset('open');
        $this->openStart();
    }

    public function updatedView(): void
    {
        $this->view = in_array($this->view, ['people', 'positions'], true) ? $this->view : 'people';
        $this->focus = null;
        $this->search = '';
        $this->open = [];
        $this->openStart();
    }

    public function toggle(int $id): void
    {
        $this->open = in_array($id, $this->open, true)
            ? array_values(array_diff($this->open, [$id]))
            : [...$this->open, $id];
    }

    public function collapseAll(): void
    {
        $this->focus = null;
        $this->open = [];
    }

    /** Jump to someone from the search results. */
    public function show(int $id): void
    {
        $this->focus = $id;
        $this->search = '';
        $this->openStart();
    }

    /** At the start: the top level open; with a focus, also everything above the focused node. */
    private function openStart(): void
    {
        ['parents' => $parents, 'byParent' => $byParent] = $this->tree();

        $open = array_keys(array_filter($parents, fn ($p) => $p === 0));   // roots
        if ($this->focus !== null && isset($parents[$this->focus])) {
            $current = $parents[$this->focus];
            while ($current !== 0 && ! in_array($current, $open, true)) {
                $open[] = $current;
                $current = $parents[$current] ?? 0;
            }
        }

        // Only nodes that have children are worth "opening".
        $this->open = array_values(array_unique([...$this->open, ...array_filter($open, fn ($id) => isset($byParent[$id]))]));
    }

    /**
     * The whole chart as plain arrays (one query):
     *   nodes     id => [id, title, subtitle, photo, url, vacant]
     *   parents   id => parent id (0 = top)
     *   byParent  parent id => list of child ids
     *
     * @return array{nodes: array<int, array<string, mixed>>, parents: array<int, int>, byParent: array<int, list<int>>}
     */
    private function tree(): array
    {
        return $this->view === 'positions' ? $this->positionTree() : $this->peopleTree();
    }

    /** @return array{nodes: array<int, array<string, mixed>>, parents: array<int, int>, byParent: array<int, list<int>>} */
    private function peopleTree(): array
    {
        $employees = Employee::query()
            ->where('status', '!=', EmployeeStatus::Separated)
            ->with('designation:id,name')
            ->orderBy('first_name')
            ->get(['id', 'code', 'first_name', 'last_name', 'designation_id', 'supervisor_id', 'photo_path']);

        $nodes = [];
        foreach ($employees as $e) {
            $nodes[$e->id] = [
                'id' => $e->id,
                'title' => $e->name,
                'subtitle' => $e->designation->name ?? $e->code,
                'photo' => $e->photo_url,
                'url' => route('hrm.employees.show', $e),
                'vacant' => false,
            ];
        }

        // Parent = the supervisor, if they are on the chart; otherwise this person is at the top.
        $parents = [];
        foreach ($employees as $e) {
            $parents[$e->id] = $e->supervisor_id !== null && isset($nodes[$e->supervisor_id]) ? $e->supervisor_id : 0;
        }

        return ['nodes' => $nodes, 'parents' => $parents, 'byParent' => $this->group($parents)];
    }

    /** @return array{nodes: array<int, array<string, mixed>>, parents: array<int, int>, byParent: array<int, list<int>>} */
    private function positionTree(): array
    {
        $units = ($user = Auth::user()) ? app(EmployeeVisibility::class)->orgUnitIdsFor($user) : null;

        $positions = Position::query()
            ->where('is_active', true)
            ->when($units !== null, fn ($q) => $q->whereIn('org_unit_id', $units))
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'employee_id', 'org_unit_id']);

        // Holders: names only for people you may see (the global scope on Employee decides).
        $holders = Employee::query()->whereIn('id', $positions->pluck('employee_id')->filter())->get(['id', 'first_name', 'last_name', 'photo_path']);
        $hidden = Employee::withoutGlobalScope(VisibleToCurrentUser::class)->whereIn('id', $positions->pluck('employee_id')->filter())->pluck('id');
        $holders = $holders->keyBy('id');

        $nodes = [];
        foreach ($positions as $p) {
            $holder = $p->employee_id ? $holders->get($p->employee_id) : null;
            $nodes[$p->id] = [
                'id' => $p->id,
                'title' => $p->name,
                'subtitle' => $holder->name ?? ($p->employee_id && $hidden->contains($p->employee_id) ? __('Filled') : __('Vacant')),
                'photo' => $holder?->photo_url,
                'url' => $holder ? route('hrm.employees.show', $holder) : null,
                'vacant' => $p->employee_id === null,
            ];
        }

        $parents = [];
        foreach ($positions as $p) {
            $parents[$p->id] = $p->parent_id !== null && isset($nodes[$p->parent_id]) ? $p->parent_id : 0;
        }

        return ['nodes' => $nodes, 'parents' => $parents, 'byParent' => $this->group($parents)];
    }

    /**
     * @param  array<int, int>  $parents
     * @return array<int, list<int>>
     */
    private function group(array $parents): array
    {
        $byParent = [];
        foreach ($parents as $id => $parent) {
            $byParent[$parent][] = $id;
        }

        return $byParent;
    }

    public function render(): View
    {
        Gate::authorize('viewAny', Employee::class);
        $tree = $this->tree();

        $matches = [];
        $term = mb_strtolower(trim($this->search));
        if (mb_strlen($term) >= 2) {
            foreach ($tree['nodes'] as $node) {
                if (str_contains(mb_strtolower($node['title'].' '.$node['subtitle']), $term)) {
                    $matches[] = $node;
                    if (count($matches) === 8) {
                        break;
                    }
                }
            }
        }

        return view('hrm::livewire.org-chart', [
            ...$tree,
            'roots' => $tree['byParent'][0] ?? [],
            'matches' => $matches,
        ]);
    }
}
