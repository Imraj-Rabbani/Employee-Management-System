<?php

namespace App\Modules\Workflow\Enums;

/**
 * Who approves one workflow step (workflow_steps.approver_type).
 * The engine turns this into real people in task 3.3.
 */
enum ApproverType: string
{
    case Employee = 'employee';       // one fixed employee (approver_employee_id)
    case Supervisor = 'supervisor';   // the requester's supervisor
    case OrgHead = 'org_head';        // the head of the requester's org unit
    case Role = 'role';               // anyone with a role (approver_role_id)

    public function label(): string
    {
        return match ($this) {
            self::Employee => __('Specific employee'),
            self::Supervisor => __('Supervisor of requester'),
            self::OrgHead => __('Head of org unit'),
            self::Role => __('Anyone with a role'),
        };
    }
}
