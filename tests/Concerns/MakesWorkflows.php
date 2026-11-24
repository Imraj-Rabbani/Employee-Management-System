<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStep;

/**
 * Set up the (one) workflow of an approval type in a test.
 *
 *     $this->workflowFor('leave', [$manager]);                          level 1 = Specific People: $manager
 *     $this->workflowFor('leave', [$lead, $manager]);                   two levels, one after the other
 *     $this->workflowFor('leave', [['approver_type' => ApproverType::Supervisor], $hr]);
 *     $this->workflowFor($type, [$a], ['allow_reject' => false]);       workflow settings
 *
 * A User level becomes "Specific People" for that login: an employee is made for them when they have none.
 * Needs the company to be active (tenancy switched).
 */
trait MakesWorkflows
{
    /**
     * @param  list<User|array<string, mixed>>  $levels
     * @param  array<string, mixed>  $settings
     */
    protected function workflowFor(string|ApprovalType $type, array $levels, array $settings = []): Workflow
    {
        $type = is_string($type) ? ApprovalType::forKey($type) : $type;
        $workflow = Workflow::create(['approval_type_id' => $type?->id, ...$settings]);

        foreach (array_values($levels) as $i => $level) {
            WorkflowStep::create([
                'workflow_id' => $workflow->id,
                'level' => $i + 1,
                ...($level instanceof User
                    ? ['approver_type' => ApproverType::Employee, 'approver_employee_id' => $this->employeeOf($level)->id]
                    : $level),
            ]);
        }

        return $workflow->load('steps');
    }

    /** The employee record of a login; made (active, named like the login) when there is none. */
    protected function employeeOf(User $user): Employee
    {
        return Employee::withoutGlobalScope(VisibleToCurrentUser::class)->where('user_id', $user->id)->first()
            ?? Employee::factory()->create(['user_id' => $user->id, 'first_name' => $user->name, 'last_name' => '']);
    }
}
