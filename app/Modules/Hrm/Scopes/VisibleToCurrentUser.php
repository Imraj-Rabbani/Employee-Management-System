<?php

namespace App\Modules\Hrm\Scopes;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Services\EmployeeVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * A GLOBAL SCOPE: a where-clause Eloquent adds to EVERY query of the model it is attached to
 * (Employee::all(), Employee::find(5), $employee->subordinates, route model binding, DataTable ...).
 * So a user limited to Department "Finance" cannot see a Sales employee, even if a screen forgets to filter.
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

        $ids = app(EmployeeVisibility::class)->entityIdsFor($user);
        if ($ids === null) {
            return;
        }

        $table = $model->getTable();

        // Wrapped in one where(...) so it becomes
        // "AND (id IN (SELECT employee_id FROM employee_entities WHERE entity_id IN (...) AND current) OR user_id = 5)"
        // and cannot mix with other or-conditions of the query.
        $builder->where(fn (Builder $q) => $q
            ->whereIn("{$table}.id", Employee::idsInEntities($ids))   // empty list → nothing
            ->orWhere("{$table}.user_id", $user->id));
    }
}
