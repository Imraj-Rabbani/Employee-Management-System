<?php

namespace App\Modules\Core\Livewire;

use App\Core\Exports\ArrayExport;
use App\Models\User;
use App\Modules\Core\Models\UserRole;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Setup → Users: search, sort, status filter, pages, select rows + bulk actions, Excel export.
 * View: app/Modules/Core/resources/views/livewire/users-table.blade.php
 */
class UsersTable extends Component
{
    use WithPagination;

    // #[Url] keeps the value in the address bar (?q=rahim), so a filtered list can be bookmarked.
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    /** '' = all, 'yes' = verified, 'no' = not verified */
    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    /** @var list<string> ids of the ticked rows */
    public array $selected = [];

    /** The tick box in the table header: ticks every row on this page. */
    public bool $selectPage = false;

    /** Columns the user may sort by. Anything else sent from the browser is ignored. */
    private const SORTABLE = ['name', 'email', 'created_at'];

    // ----------------------------------------------------------------- actions from the page

    public function sort(string $field): void
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }

        // Clicking the same column again flips the direction.
        $this->sortDir = $this->sortBy === $field && $this->sortDir === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $field;
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status');
        $this->resetPage();
        $this->clearSelection();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->selectPage = false;
    }

    public function export(): BinaryFileResponse
    {
        $roleNames = $this->roleNames();

        $rows = $this->filteredQuery()->get()->map(fn (User $user) => [
            $user->name,
            $user->email,
            $roleNames[$user->id] ?? __('No role'),
            $user->email_verified_at ? __('Verified') : __('Not verified'),
            format_date($user->created_at),
        ])->all();

        return Excel::download(
            new ArrayExport([__('Name'), __('Email'), __('Role'), __('Status'), __('Joined')], $rows),
            'users-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Bulk action buttons call this, e.g. runBulkAction('verify'). */
    public function runBulkAction(string $action): void
    {
        abort_unless(in_array($action, ['verify', 'delete'], true), 403);
        Gate::authorize('core.users.manage');

        // Only users of this company can be touched: the ids go through query().
        $users = $this->query()->whereKey($this->selected);

        if ($action === 'verify') {
            $count = $users->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
            $this->dispatch('toast', message: trans_choice(':count user verified|:count users verified', $count), variant: 'success');
        } else {
            // Never delete yourself from here.
            $count = $users->whereKeyNot(auth()->id())->delete();
            $this->dispatch('toast', message: trans_choice(':count user deleted|:count users deleted', $count), variant: 'success');
        }

        $this->clearSelection();
    }

    /** The role form saved: re-render so the new role name shows. */
    #[On('user-role-saved')]
    public function refreshList(): void {}

    // ----------------------------------------------------------------- Livewire hooks

    /** Typing a search, picking a filter or a page size: back to page 1, nothing ticked. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'perPage'], true)) {
            $this->resetPage();
            $this->clearSelection();
        }
    }

    public function updatedSelectPage(bool $value): void
    {
        $this->selected = $value
            ? $this->rows()->getCollection()->map(fn (User $user) => (string) $user->id)->all()
            : [];
    }

    // ----------------------------------------------------------------- the rows

    /**
     * Only the users of the current company. Users live in the central database,
     * so they are filtered by tenant_id.
     *
     * @return Builder<User>
     */
    private function query(): Builder
    {
        $tenant = tenant();

        // Without a company, where('tenant_id', null) would list the platform users. Refuse instead.
        abort_if($tenant === null, 403);

        return User::query()->where('tenant_id', $tenant->id);
    }

    /** @return Builder<User> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        if (trim($this->search) !== '') {
            // addcslashes: a search for "50%" means the text "50%", not "50 followed by anything".
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like));
        }

        if ($this->status === 'yes') {
            $query->whereNotNull('email_verified_at');
        } elseif ($this->status === 'no') {
            $query->whereNull('email_verified_at');
        }

        if (in_array($this->sortBy, self::SORTABLE, true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        }

        return $query;
    }

    /** @return LengthAwarePaginator<int, User> */
    private function rows(): LengthAwarePaginator
    {
        // A page size typed into the URL that is not offered falls back to 10.
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;

        return $this->filteredQuery()->paginate($perPage);
    }

    /**
     * Roles live in the tenant DB and users in the central DB, so SQL cannot join them.
     * Load every user's role name in one query instead: [user id => role name].
     *
     * @return array<int, string>
     */
    private function roleNames(): array
    {
        return UserRole::with('role')->get()
            ->mapWithKeys(fn (UserRole $userRole) => [$userRole->user_id => (string) $userRole->role?->name])
            ->all();
    }

    public function render(): View
    {
        return view('core::livewire.users-table', [
            'rows' => $this->rows(),
            'roleNames' => $this->roleNames(),
            'canManage' => Gate::allows('core.users.manage'),
        ]);
    }
}
