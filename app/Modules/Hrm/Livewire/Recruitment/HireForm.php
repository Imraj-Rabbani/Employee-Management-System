<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Modules\Hrm\Actions\HireApplicant;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\JobOffer;
use App\Modules\Hrm\Models\Position;
use App\Modules\Hrm\Services\EmployeeVisibility;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Hire (modal "hire-form") on an ACCEPTED offer's page: the few things the offer does not say, then
 * HireApplicant makes the employee and the browser opens their profile. [REC-07]
 *
 *     $dispatch('hire', { offerId: 3 })
 *
 * Needs BOTH hrm.recruitment.manage (the page) and permission to add employees (EmployeePolicy::create).
 * Org units are limited to the ones the user can see, otherwise they would create someone they cannot open.
 */
class HireForm extends Component
{
    #[Locked]
    public ?int $offerId = null;

    /** @var array<string, mixed> code, join_date, org_unit_id, position_id, office_email */
    public array $form = [];

    #[On('hire')]
    public function open(int $offerId, HireApplicant $hire): void
    {
        Gate::authorize('create', Employee::class);
        $offer = $this->offer($offerId);

        $this->offerId = $offer->id;
        $this->form = array_map(fn ($v) => $v ?? '', $hire->defaults($offer));
        if (! array_key_exists((int) $this->form['org_unit_id'], $this->orgUnits())) {
            // Safety net: normally unreachable (whoever can see the offer can see its request's unit),
            // but if the two rules ever drift apart the user picks a unit instead of hiring outside their access.
            $this->form['org_unit_id'] = '';
            $this->form['position_id'] = '';
        }
        $this->resetValidation();

        Flux::modal('hire-form')->show();
    }

    /** Another org unit: the position belonged to the old one. */
    public function updatedFormOrgUnitId(): void
    {
        $this->form['position_id'] = '';
    }

    public function save(HireApplicant $hire): void
    {
        Gate::authorize('create', Employee::class);
        $offer = $this->offer((int) $this->offerId);
        $unitId = (int) ($this->form['org_unit_id'] ?: 0);

        $values = $this->validate([
            'form.code' => ['required', 'string', 'max:50'],
            'form.join_date' => ['required', 'date'],
            'form.org_unit_id' => ['required', Rule::in(array_keys($this->orgUnits()))],
            'form.position_id' => ['nullable', Rule::exists(Position::class, 'id')->where('org_unit_id', $unitId)],
            'form.office_email' => ['nullable', 'email', 'max:150'],
        ], [], [
            'form.code' => __('employee code'), 'form.join_date' => __('join date'),
            'form.org_unit_id' => __('org unit'), 'form.position_id' => __('position'), 'form.office_email' => __('office email'),
        ])['form'];
        $values = array_map(fn ($v) => $v === '' ? null : $v, $values);

        if ($problem = $hire->problem($offer, $values)) {
            $this->addError('form.'.$problem['field'], $problem['message']);

            return;
        }

        $employee = $hire->handle($offer, $values, Auth::id());

        session()->flash('toast', ['message' => __(':name hired as :code. Complete the profile here.', ['name' => $employee->name, 'code' => $employee->code]), 'variant' => 'success']);
        $this->redirectRoute('hrm.employees.show', ['employee' => $employee->id], navigate: true);
    }

    private function offer(int $id): JobOffer
    {
        return JobOffer::query()->whereHas('application', fn ($q) => $q->visibleTo(Auth::user()))
            ->with(['application.applicant', 'application.jobPosting.manpowerRequest'])->findOrFail($id);
    }

    /** @return array<int, string> */
    private function orgUnits(): array
    {
        return app(EmployeeVisibility::class)->orgUnitOptionsFor(Auth::user());
    }

    public function render(): View
    {
        $offer = $this->offerId ? $this->offer($this->offerId) : null;
        $unitId = (int) ($this->form['org_unit_id'] ?? 0);

        return view('hrm::livewire.recruitment.hire-form', [
            'offer' => $offer,
            'units' => $this->orgUnits(),
            // Free seats of the chosen unit (and the one already chosen).
            'positions' => $unitId ? Position::where('org_unit_id', $unitId)->whereNull('employee_id')->orderBy('name')->pluck('name', 'id')->all() : [],
        ]);
    }
}
