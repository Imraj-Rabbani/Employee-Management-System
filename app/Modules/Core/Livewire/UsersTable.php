<?php

namespace App\Modules\Core\Livewire;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Core\Livewire\Table\SelectFilter;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

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
        return [
            'verify' => ['label' => __('Mark verified')],
            'delete' => ['label' => __('Delete'), 'confirm' => __('Delete the selected users?'), 'variant' => 'danger'],
        ];
    }

    /** @param  list<string>  $ids */
    public function bulkVerify(array $ids): void
    {
        $count = $this->query()->whereKey($ids)->whereNull('email_verified_at')->update(['email_verified_at' => now()]);

        $this->dispatch('toast', message: trans_choice(':count user verified|:count users verified', $count), variant: 'success');
    }

    /** @param  list<string>  $ids */
    public function bulkDelete(array $ids): void
    {
        // Never delete yourself from here.
        $count = $this->query()->whereKey($ids)->whereKeyNot(auth()->id())->delete();

        $this->dispatch('toast', message: trans_choice(':count user deleted|:count users deleted', $count), variant: 'success');
    }
}
