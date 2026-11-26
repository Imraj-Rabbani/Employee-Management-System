<?php

namespace App\Modules\Hrm\Actions;

use App\Core\Support\TenantUploads;
use App\Modules\Hrm\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

/**
 * Deletes an employee record for good: meant for one entered by mistake or twice.
 *
 *     $delete = app(DeleteEmployee::class);
 *     if ($problem = $delete->problem($employee)) { ...show $problem... }
 *     $delete->handle($employee);
 *
 * Someone with real history stays (attendance, payroll, leave, approvals, interviews, a hire, a login):
 * deleting them would erase or break that history. For people who left, set the status to
 * Resigned / Terminated instead.
 *
 * What goes with the record (database cascade): org-unit history, salary set-up, education, work
 * history, family, nominees, bank accounts, documents, leave balances. Their files (photo, signature,
 * document attachments) are removed after the delete. A seat / an entity they headed / people they
 * supervised simply lose the link (null on delete).
 */
class DeleteEmployee
{
    /** [table, column, why it blocks]: checked in this order, the first hit is the message. */
    private const BLOCKERS = [
        ['payroll_run_employees', 'employee_id', 'has payroll records'],
        ['payroll_items', 'employee_id', 'has payroll records'],
        ['payroll_run_amounts', 'employee_id', 'has payroll records'],
        ['payroll_arrears', 'employee_id', 'has payroll records'],
        ['payroll_adjustments', 'employee_id', 'has payroll adjustments'],
        ['attendance_days', 'employee_id', 'has attendance records'],
        ['punch_logs', 'employee_id', 'has attendance punches'],
        ['roster_items', 'employee_id', 'is on a roster'],
        ['attendance_corrections', 'employee_id', 'has attendance corrections'],
        ['overtime_requests', 'employee_id', 'has overtime requests'],
        ['leave_applications', 'employee_id', 'has leave applications'],
        ['leave_encashments', 'employee_id', 'has leave encashments'],
        ['interview_panelists', 'employee_id', 'is on an interview panel'],
        ['applications', 'hired_employee_id', 'was hired through recruitment'],
        ['approvals', 'requester_employee_id', 'is in the approval history'],
        ['approvals', 'approver_employee_id', 'is in the approval history'],
        ['workflow_steps', 'approver_employee_id', 'is chosen as an approver in Workflow Setup'],
    ];

    public function __construct(private TenantUploads $uploads) {}

    /** Why this employee cannot be deleted, or null. */
    public function problem(Employee $employee): ?string
    {
        if ($employee->user_id !== null) {
            return __('Has a login. Remove the login first (Login box on the profile).');
        }

        $db = DB::connection('tenant');
        foreach (self::BLOCKERS as [$table, $column, $why]) {
            if (Schema::connection('tenant')->hasTable($table) && $db->table($table)->where($column, $employee->id)->exists()) {
                return __(':why. Set the status to Resigned or Terminated instead.', ['why' => ucfirst(__($why))]);
            }
        }

        return null;
    }

    public function handle(Employee $employee): void
    {
        if (($problem = $this->problem($employee)) !== null) {
            throw new LogicException($problem);
        }

        // Files are removed only once the rows are gone for good.
        $files = array_filter([
            $employee->photo_path,
            $employee->signature_path,
            ...DB::connection('tenant')->table('employee_documents')->where('employee_id', $employee->id)->pluck('attachment_path')->all(),
        ]);

        DB::connection('tenant')->transaction(fn () => $employee->delete());

        foreach ($files as $path) {
            $this->uploads->delete($path);
        }
    }
}
