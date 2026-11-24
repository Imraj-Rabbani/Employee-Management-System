<?php

namespace App\Modules\Workflow\Enums;

/**
 * Who approves one level of a workflow (workflow_steps.approver_type).
 * The workflow engine turns this into a real person (one resolver class per case).
 *
 * "The employee" = the employee the document is about (approvalContext()['employee_id']),
 * e.g. the person asking for leave.
 */
enum ApproverType: string
{
    case Supervisor = 'supervisor';                       // the employee's reporting supervisor
    case EntityHead = 'entity_head';                      // head of the employee's entity of one type (approver_entity_type_id)
    case SpecificEntityHead = 'specific_entity_head';     // head of one chosen entity (approver_entity_id)
    case Employee = 'employee';                           // one chosen employee (approver_employee_id)

    public function label(): string
    {
        return match ($this) {
            self::Supervisor => __('Employee\'s Reporting Supervisor'),
            self::EntityHead => __('Employee\'s Entity Head'),
            self::SpecificEntityHead => __('Specific Entity Head'),
            self::Employee => __('Specific People'),
        };
    }

    /** The level needs an entity type (Department, Branch ...). */
    public function needsEntityType(): bool
    {
        return $this === self::EntityHead || $this === self::SpecificEntityHead;
    }
}
