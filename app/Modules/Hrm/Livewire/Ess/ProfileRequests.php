<?php

namespace App\Modules\Hrm\Livewire\Ess;

use App\Modules\Hrm\Enums\BloodGroup;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Enums\MaritalStatus;
use App\Modules\Hrm\Enums\Religion;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\ProfileUpdateRequest;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Exceptions\WorkflowException;
use BackedEnum;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Self Service → My Profile Update Requests: my requests and "Request a change". [EMP-08]
 *
 * Works only on the employee linked to the logged-in user (5.7); nobody can send a request for someone else.
 * One open request at a time, so the "old" values stored with a request stay true until it is decided.
 */
class ProfileRequests extends Component
{
    /** @var array<string, mixed> field => value in the form */
    public array $form = [];

    public string $reason = '';

    public function open(): void
    {
        $employee = $this->mine();

        if ($this->hasOpenRequest($employee)) {
            $this->dispatch('toast', message: __('You already have a request waiting for approval.'), variant: 'error');

            return;
        }

        $this->form = $this->currentValues($employee);
        $this->reason = '';
        $this->resetValidation();

        Flux::modal('profile-request-form')->show();
    }

    public function submit(): void
    {
        $employee = $this->mine();

        if ($this->hasOpenRequest($employee)) {
            $this->addError('reason', __('You already have a request waiting for approval.'));

            return;
        }

        $rules = Arr::only(Employee::personalRules($employee->id), ProfileUpdateRequest::FIELDS);
        $this->validate([
            ...collect($rules)->mapWithKeys(fn ($r, $f) => ["form.{$f}" => $r])->all(),
            'reason' => ['nullable', 'string', 'max:500'],
        ], attributes: collect(ProfileUpdateRequest::FIELDS)->mapWithKeys(fn ($f) => ["form.{$f}" => ProfileUpdateRequest::label($f)])->all());

        // Only what really changes. Compared as text, so 1 (int) and "1" (form) count as equal.
        $current = $this->currentValues($employee);
        $changes = [];
        foreach (ProfileUpdateRequest::FIELDS as $field) {
            $new = ($this->form[$field] ?? '') === '' ? null : $this->form[$field];
            $old = $current[$field] === '' ? null : $current[$field];
            if ((string) $new !== (string) $old) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        if ($changes === []) {
            $this->addError('reason', __('You did not change anything.'));

            return;
        }

        $request = ProfileUpdateRequest::create([
            'employee_id' => $employee->id,
            'requested_by_user_id' => Auth::id(),
            'field_changes' => $changes,
            'reason' => trim($this->reason) ?: null,
        ]);

        try {
            $request->submitForApproval();
        } catch (WorkflowException $e) {
            $request->delete();   // never keep a request that nobody can approve
            $this->addError('reason', $e->getMessage());

            return;
        }

        Flux::modal('profile-request-form')->close();
        $this->dispatch('toast', message: __('Request sent for approval.'), variant: 'success');
    }

    /** The employee linked to this login, or 404 (the page shows a message instead of the button). */
    private function mine(): Employee
    {
        return Employee::query()->where('user_id', Auth::id())->firstOrFail();
    }

    private function hasOpenRequest(Employee $employee): bool
    {
        return ProfileUpdateRequest::where('employee_id', $employee->id)->whereNull('applied_at')->get()
            ->contains(fn (ProfileUpdateRequest $r) => $r->approvalStatus() === ApprovalStatus::Pending);
    }

    /**
     * The employee's current values in form shape (enums as numbers, dates as 2026-11-08, null as '').
     *
     * @return array<string, mixed>
     */
    private function currentValues(Employee $employee): array
    {
        $values = [];
        foreach (ProfileUpdateRequest::FIELDS as $field) {
            $value = $employee->getAttribute($field);
            $values[$field] = match (true) {
                $value instanceof BackedEnum => $value->value,
                $value instanceof Carbon => $value->toDateString(),
                $value === null => '',
                default => $value,
            };
        }

        return $values;
    }

    public function render(): View
    {
        $employee = Employee::query()->where('user_id', Auth::id())->first();

        return view('hrm::livewire.ess.profile-requests', [
            'employee' => $employee,
            'requests' => $employee
                ? ProfileUpdateRequest::where('employee_id', $employee->id)->with('approvals')->latest('id')->get()
                : collect(),
            'genders' => Gender::options(),
            'maritalStatuses' => MaritalStatus::options(),
            'bloodGroups' => BloodGroup::options(),
            'religions' => Religion::options(),
        ]);
    }
}
