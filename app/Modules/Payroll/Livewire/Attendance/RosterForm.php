<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Modules\Hrm\Services\EmployeeVisibility;
use App\Modules\Payroll\Models\Roster;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * New / Edit / Delete roster: the HEADER only (org unit, title, dates). The cells are filled in the grid (7.2c).
 * Modal "roster-form". [TA-03]
 *
 * Rules beyond the fields:
 *   - the org unit must be one the user can see (EmployeeVisibility), and cannot change once cells exist
 *   - at most MAX_DAYS days (one screen of grid; a month is the usual period)
 *   - only a draft or a roster sent back (Review) can be edited; only a draft deleted
 *   - shortening the period removes the cells outside it
 */
class RosterForm extends Component
{
    public const MAX_DAYS = 62;

    /** null = creating a new roster */
    #[Locked]
    public ?int $recordId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** Starts as next month for the first unit: the usual case. */
    #[On('create-roster')]
    public function create(): void
    {
        Gate::authorize('payroll.roster.manage');
        $next = now()->addMonthNoOverflow();

        $this->recordId = null;
        $this->form = [
            'org_unit_id' => (string) (array_key_first($this->orgUnits()) ?? ''),
            'title' => $next->translatedFormat('F Y'),
            'from_date' => $next->copy()->startOfMonth()->toDateString(),
            'to_date' => $next->copy()->endOfMonth()->toDateString(),
            'note' => '',
        ];
        $this->resetValidation();

        Flux::modal('roster-form')->show();
    }

    #[On('edit-roster')]
    public function edit(int $id): void
    {
        $roster = $this->find($id);

        $this->recordId = $roster->id;
        $this->form = [
            'org_unit_id' => (string) $roster->org_unit_id,
            'title' => $roster->title,
            'from_date' => $roster->from_date->toDateString(),
            'to_date' => $roster->to_date->toDateString(),
            'note' => $roster->note ?? '',
        ];
        $this->resetValidation();

        Flux::modal('roster-form')->show();
    }

    public function save(): void
    {
        $roster = $this->recordId ? $this->find($this->recordId) : null;
        if ($roster && ! $roster->isEditable()) {
            $this->addError('form.title', __('This roster is waiting for approval or already approved, so it cannot be changed.'));

            return;
        }

        $values = $this->validate([
            'form.org_unit_id' => ['required', Rule::in(array_keys($this->orgUnits()))],
            'form.title' => ['required', 'string', 'max:150'],
            'form.from_date' => ['required', 'date'],
            'form.to_date' => ['required', 'date', 'after_or_equal:form.from_date'],
            'form.note' => ['nullable', 'string', 'max:2000'],
        ], attributes: [
            'form.org_unit_id' => __('org unit'), 'form.from_date' => __('from'), 'form.to_date' => __('to'),
        ])['form'];
        $values['note'] = $values['note'] === '' ? null : $values['note'];

        $from = Carbon::parse($values['from_date']);
        $to = Carbon::parse($values['to_date']);
        if ($from->diffInDays($to) + 1 > self::MAX_DAYS) {
            $this->addError('form.to_date', __('A roster can cover at most :n days.', ['n' => self::MAX_DAYS]));

            return;
        }
        if ($roster && (int) $values['org_unit_id'] !== $roster->org_unit_id && $roster->items()->exists()) {
            $this->addError('form.org_unit_id', __('The grid already has shifts for this unit\'s employees. Make a new roster for another unit.'));

            return;
        }

        if ($roster) {
            $roster->getConnection()->transaction(function () use ($roster, $values, $from, $to) {
                $roster->update($values);
                // Cells outside the new period are no longer part of the plan.
                $roster->items()->where(fn ($q) => $q->whereDate('date', '<', $from)->orWhereDate('date', '>', $to))->delete();
            });
        } else {
            $roster = Roster::create([...$values, 'requested_by_user_id' => Auth::id()]);
        }

        Flux::modal('roster-form')->close();
        // flash(): the toast survives the redirect. The roster's page shows the (new) header and its grid.
        session()->flash('toast', ['message' => __('Roster :no saved.', ['no' => $roster->number()]), 'variant' => 'success']);
        $this->redirectRoute('payroll.attendance.rosters.show', ['id' => $roster->id], navigate: true);
    }

    public function delete(): void
    {
        $roster = $this->find((int) $this->recordId);
        if (! $roster->isDraft()) {
            $this->addError('form.title', __('Only a draft can be deleted. This one has an approval history.'));

            return;
        }

        // Cells go with it (cascade).
        $roster->getConnection()->transaction(fn () => $roster->delete());

        session()->flash('toast', ['message' => __('Roster :no deleted.', ['no' => $roster->number()]), 'variant' => 'success']);
        $this->redirectRoute('payroll.attendance.rosters', navigate: true);
    }

    /** Only rosters the user may see: another unit's id in the event gives 404. */
    private function find(int $id): Roster
    {
        Gate::authorize('payroll.roster.manage');

        return Roster::query()->visibleTo(Auth::user())->findOrFail($id);
    }

    /** @return array<int, string> */
    private function orgUnits(): array
    {
        return app(EmployeeVisibility::class)->orgUnitOptionsFor(Auth::user());
    }

    public function render(): View
    {
        $roster = $this->recordId ? Roster::query()->visibleTo(Auth::user())->find($this->recordId) : null;

        return view('payroll::livewire.attendance.roster-form', [
            'roster' => $roster,
            'units' => $this->orgUnits(),
        ]);
    }
}
