<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Modules\Payroll\Actions\SaveRosterCells;
use App\Modules\Payroll\Imports\RosterSheetReader;
use App\Modules\Payroll\Models\Roster;
use App\Modules\Payroll\Models\Shift;
use App\Modules\Payroll\Services\RosterAutoFill;
use App\Modules\Workflow\Exceptions\WorkflowException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The roster's grid: employees × days (7.2c), plus its actions: Send for approval / Plan a change (7.2d)
 * and Excel import (7.2e). [TA-03]
 *
 * The BROWSER holds the grid while you paint (Alpine, nothing is sent per click). Save / Fill send the whole
 * grid as an argument and get the new grid back as the return value ($wire.save(cells) → cells). So the
 * ~1,500 cells are not a public property travelling with every request.
 * Grid format everywhere: Roster::cellMatrix(), checked and written by SaveRosterCells.
 */
class RosterGrid extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $rosterId;

    public ?TemporaryUploadedFile $file = null;

    /** @var list<string> problems of the last import, shown in the import modal */
    public array $importProblems = [];

    public function mount(int $rosterId): void
    {
        $this->rosterId = $rosterId;
        $this->roster();   // 404 / 403 right away
    }

    /**
     * @param  array<int|string, array<string, string|null>>  $cells  the grid from the browser
     * @return array<int, array<string, string>>|null the saved grid, or null when refused (toast says why)
     */
    public function save(array $cells): ?array
    {
        $action = app(SaveRosterCells::class);
        $roster = $this->roster();
        if ($problem = $action->problem($roster, $cells)) {
            $this->dispatch('toast', message: $problem, variant: 'danger');

            return null;
        }

        $planned = $action->handle($roster, $cells);
        $this->dispatch('toast', message: trans_choice('Saved. :count cell planned.|Saved. :count cells planned.', $planned), variant: 'success');

        return $roster->cellMatrix();
    }

    /**
     * Fills the EMPTY cells from each employee's shift, weekend and holiday calendar. Not saved yet.
     *
     * @param  array<int|string, array<string, string>>  $cells  the grid from the browser
     * @return array<int|string, array<string, string>> the filled grid
     */
    public function fillEmpty(array $cells): array
    {
        $roster = $this->roster();
        if (! $roster->isEditable()) {
            return $cells;
        }

        [$cells, $filled] = app(RosterAutoFill::class)->fill($roster, $cells);
        $this->dispatch('toast', message: $filled > 0
            ? trans_choice(':count cell filled. Check it, then press Save.|:count cells filled. Check them, then press Save.', $filled)
            : __('Nothing to fill: every cell is planned, or nobody has a shift (and there is no default shift).'));

        return $cells;
    }

    /**
     * Sends the SAVED roster into the "roster" workflow (Workflow → Setup). The page warns first when the
     * grid has unsaved changes. The engine's messages (no workflow, nobody to approve) are for users.
     */
    public function submit(): void
    {
        $roster = $this->roster();
        if (! $roster->isEditable()) {
            $this->dispatch('toast', message: __('This roster is already waiting for approval or answered.'), variant: 'danger');

            return;
        }
        if (! $roster->items()->exists()) {
            $this->dispatch('toast', message: __('Plan at least one cell and save, then send the roster.'), variant: 'danger');

            return;
        }

        try {
            $roster->submitForApproval(Auth::user());
        } catch (WorkflowException $e) {
            $this->dispatch('toast', message: $e->getMessage(), variant: 'danger');

            return;
        }

        session()->flash('toast', ['message' => __('Roster :no sent for approval.', ['no' => $roster->number()]), 'variant' => 'success']);
        $this->redirectRoute('payroll.attendance.rosters.show', ['id' => $roster->id], navigate: true);
    }

    /**
     * An approved roster is never edited: a change is a NEW draft roster, a copy with its cells, pointing at
     * this one. Once that is approved, its cells win (RosterItem::planFor takes the newest approval).
     */
    public function planChange(): void
    {
        $roster = $this->roster();
        if (! $roster->isApproved()) {
            return;
        }

        $copy = $roster->getConnection()->transaction(function () use ($roster) {
            $copy = Roster::create([
                'org_unit_id' => $roster->org_unit_id,
                'replaces_roster_id' => $roster->id,
                'title' => mb_substr(__(':title (change)', ['title' => $roster->title]), 0, 150),
                'from_date' => $roster->from_date,
                'to_date' => $roster->to_date,
                'requested_by_user_id' => Auth::id(),
            ]);
            app(SaveRosterCells::class)->handle($copy, $roster->cellMatrix());

            return $copy;
        });

        session()->flash('toast', ['message' => __('Roster :no created as a change of :old. Change the grid, then send it for approval.', [
            'no' => $copy->number(), 'old' => $roster->number(),
        ]), 'variant' => 'success']);
        $this->redirectRoute('payroll.attendance.rosters.show', ['id' => $copy->id], navigate: true);
    }

    /** Reads the uploaded Excel file and saves its cells, or lists every problem and changes nothing. */
    public function import(RosterSheetReader $reader, SaveRosterCells $action): void
    {
        $roster = $this->roster();
        $this->importProblems = [];
        $this->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120']], attributes: ['file' => __('file')]);

        [$cells, $problems] = $reader->read($roster, $this->file->getRealPath());
        $problem = $problems === [] ? $action->problem($roster, $cells) : null;
        if ($problems !== [] || $problem !== null) {
            $this->importProblems = $problem ? [$problem] : $problems;

            return;
        }

        $action->handle($roster, $cells);
        $this->reset('file');
        Flux::modal('roster-import')->close();
        // The grid in the browser takes the saved cells (and forgets unsaved painting).
        $this->dispatch('roster-cells', cells: $roster->cellMatrix());
        $this->dispatch('toast', message: trans_choice(':count employee imported.|:count employees imported.', count($cells)), variant: 'success');
    }

    /** Only rosters the user may see (404 otherwise); every action needs the permission. */
    private function roster(): Roster
    {
        Gate::authorize('payroll.roster.manage');

        return Roster::query()->visibleTo(Auth::user())->findOrFail($this->rosterId);
    }

    public function render(): View
    {
        $roster = $this->roster();
        $employees = $roster->gridEmployees();
        $usedShiftIds = $roster->items()->whereNotNull('shift_id')->distinct()->pluck('shift_id')->all();
        $shifts = Shift::query()->where('is_active', true)->orWhereIn('id', $usedShiftIds)->orderBy('code')->get();

        return view('payroll::livewire.attendance.roster-grid', [
            'roster' => $roster,
            'editable' => $roster->isEditable(),
            'cells' => $roster->cellMatrix($employees),
            'employees' => $employees,
            'dates' => $roster->dates(),
            'shifts' => $shifts,
            // For the cells in the browser: shift id => [code, title]
            'shiftCodes' => $shifts->mapWithKeys(fn (Shift $s) => [$s->id => [$s->code, $s->optionLabel()]])->all(),
        ]);
    }
}
