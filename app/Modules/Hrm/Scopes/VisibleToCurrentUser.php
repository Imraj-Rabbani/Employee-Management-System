<?php

namespace App\Modules\Hrm\Scopes;

use App\Models\User;
use App\Modules\Hrm\Services\EmployeeVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * A GLOBAL SCOPE: a where-clause Eloquent adds to EVERY query of the model it is attached to
 * (Employee::all(), Employee::find(5), $unit->employees, route model binding, DataTable ...).
 * So a user limited to "Finance" cannot see a Sales employee, even if a screen forgets to filter.
 *
 *     Employee::count()                                         only the employees I may see
 *     Employee::withoutGlobalScope(VisibleToCurrentUser::class) everyone (system code only: payroll run, reports for admins ...)
 *
 * Nobody logged in (artisan, queued jobs, the scheduler) → no limit: those are system tasks.
 * The rules themselves are in EmployeeVisibility.
 */
class VisibleToCurrentUser implements Scope
{
    /** @param  Builder<Model>  $builder */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        $ids = app(EmployeeVisibility::class)->orgUnitIdsFor($user);
        if ($ids === null) {
            return;
        }

        $table = $model->getTable();

        // Wrapped in one where(...) so it becomes "AND (org_unit_id IN (...) OR user_id = 5)"
        // and cannot mix with other or-conditions of the query.
        $builder->where(fn (Builder $q) => $q
            ->whereIn("{$table}.org_unit_id", $ids)   // empty list → "0 = 1": nothing
            ->orWhere("{$table}.user_id", $user->id));
    }
}
