<?php

namespace App\Modules\Core\Livewire;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Core\Livewire\Table\SelectFilter;
use App\Models\User;
use App\Modules\Core\Models\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

/**
 * Setup → Users. The first list screen built on the DataTable base.
 */
class UsersTable extends DataTable
{
    /**
     * Only the users of the current company. Users live in the central database,
     * so they are filtered by tenant_id (the tenant connection is not involved here).
     *
     * @return Builder<User>
     */
    protected function query(): Builder
    {
        $tenant = tenant();

        // Without a company, where('tenant_id', null) would list the platform users. Refuse instead.
        abort_if($tenant === null, 403);

        return User::query()->where('tenant_id', $tenant->id);
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Name'), 'name')->sortable()->searchable()->alwaysVisible(),
            Column::make(__('Email'), 'email')->sortable()->searchable(),
            // Roles live in the tenant DB, users in the central DB: no SQL join, so look them up in one query (roleNames()).
            Column::make(__('Role'), 'role')
                ->format(fn (User $user) => new HtmlString(Blade::render(
                    '@if ($canManage)<button type="button" x-on:click="$dispatch(\'edit-user-role\', { id: {{ $id }} })" class="text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $role ?? __(\'No role\') }}</button>@else{{ $role ?? __(\'No role\') }}@endif',
                    ['id' => $user->id, 'role' => $this->roleNames()[$user->id] ?? null, 'canManage' => Gate::allows('core.users.manage')],
                ))),
            Column::make(__('Status'), 'email_verified_at')
                ->format(fn (User $user) => new HtmlString(Blade::render(
                    '<x-ui.badge :color="$color" dot>{{ $text }}</x-ui.badge>',
                    $user->email_verified_at
                        ? ['color' => 'success', 'text' => __('Verified')]
                        : ['color' => 'warning', 'text' => __('Not verified')],
                ))),
            Column::make(__('Joined'), 'created_at')
                ->sortable()
                ->format(fn (User $user) => $user->created_at?->format('d M Y')),
        ];
    }

    /** @var array<int, string>|null user id => role name, loaded once per request */
    private ?array $roleNames = null;

    /** @return array<int, string> */
    private function roleNames(): array
    {
        return $this->roleNames ??= UserRole::with('role')->get()
            ->mapWithKeys(fn (UserRole $userRole) => [$userRole->user_id => (string) $userRole->role?->name])
            ->all();
    }

    #[On('user-role-saved')]
    public function refreshList(): void
    {
        $this->roleNames = null;
    }

    protected function filters(): array
    {
        return [
            SelectFilter::make('verified', __('Status'), ['yes' => __('Verified'), 'no' => __('Not verified')])
                ->query(fn (Builder $query, string $value) => $value === 'yes'
                    ? $query->whereNotNull('email_verified_at')
                    : $query->whereNull('email_verified_at')),
        ];
    }

    protected function bulkActions(): array
    {
        // Only for users who may change users (permission core.users.manage).
        if (! Gate::allows('core.users.manage')) {
            return [];
        }

        return [
            'verify' => ['label' => __('Mark verified')],
            'delete' => ['label' => __('Delete'), 'confirm' => __('Delete the selected users?'), 'variant' => 'danger'],
        ];
    }

    /** @param  list<string>  $ids */
    public function bulkVerify(array $ids): void
    {
        Gate::authorize('core.users.manage');

        $count = $this->query()->whereKey($ids)->whereNull('email_verified_at')->update(['email_verified_at' => now()]);

        $this->dispatch('toast', message: trans_choice(':count user verified|:count users verified', $count), variant: 'success');
    }

    /** @param  list<string>  $ids */
    public function bulkDelete(array $ids): void
    {
        Gate::authorize('core.users.manage');

        // Never delete yourself from here.
        $count = $this->query()->whereKey($ids)->whereKeyNot(auth()->id())->delete();

        $this->dispatch('toast', message: trans_choice(':count user deleted|:count users deleted', $count), variant: 'success');
    }
}
