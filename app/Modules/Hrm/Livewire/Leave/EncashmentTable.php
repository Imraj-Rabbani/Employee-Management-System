<?php

namespace App\Modules\Hrm\Livewire\Leave;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Core\Livewire\Table\SelectFilter;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveApplication;
use App\Modules\Hrm\Models\LeaveEncashment;
use App\Modules\Workflow\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * HRM → Leave → Encashments (8.6): encashments of the employees the user can see, newest first.
 * Click the number of one that was sent back to change it; "Withdraw" until payroll paid it. [LV-06]
 */
class EncashmentTable extends DataTable
{
    /** @var array<string, string> */
    protected $listeners = ['encashment-saved' => '$refresh'];

    /** @return Builder<LeaveEncashment> */
    protected function query(): Builder
    {
        return LeaveEncashment::query()
            ->with(['employee:id,code,first_name,last_name', 'leaveType:id,code,name', 'approvals'])
            ->whereIn('employee_id', Employee::query()->select('id'));
    }

    protected function defaultSort(): array
    {
        return ['id' => 'desc'];
    }

    protected function filters(): array
    {
        $pending = fn (Builder $a) => $a->where('status', ApprovalStatus::Pending);
        // The filter's Builder is untyped: the model's own scopes, as id subqueries, keep ONE definition each.
        $only = fn (Builder $q, Builder $ids) => $q->whereIn('id', $ids->select('id'));

        return [
            SelectFilter::make('status', __('Status'), [
                'waiting' => __('Waiting for approval'), 'payroll' => __('Approved · waiting for payroll'), 'paid' => __('Paid'),
                'closed' => __('Rejected or withdrawn'),
            ])->query(fn (Builder $q, string $v) => match ($v) {
                'waiting' => $only($q, LeaveEncashment::query()->active())->whereHas('approvals', $pending),
                'payroll' => $only($q, LeaveEncashment::query()->readyForPayroll()),
                'paid' => $q->whereNotNull('paid_at'),
                default => $q->where(fn ($q) => $q->whereNotNull('rejected_at')->orWhereNotNull('withdrawn_at')),
            }),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('No.'), 'id')->sortable()->alwaysVisible()->format(fn (LeaveEncashment $e) => $e->number())->opens('edit-encashment'),
            Column::make(__('Employee'), 'employee_id')->alwaysVisible()
                ->format(fn (LeaveEncashment $e) => $e->employee ? $e->employee->code.' · '.$e->employee->name : '—'),
            Column::make(__('Leave type'), 'leave_type_id')->format(fn (LeaveEncashment $e) => $e->leaveType->name ?? '—'),
            Column::make(__('Days'), 'days')->sortable()->alignRight()->format(fn (LeaveEncashment $e) => LeaveApplication::daysLabel($e->days)),
            Column::make(__('Amount'), 'amount')->alignRight()->format(fn (LeaveEncashment $e) => $e->amount ?? '—'),
            Column::make(__('Reason'), 'reason')->searchable(),
            Column::make(__('Status'), 'approved_at')->format(function (LeaveEncashment $e) {
                [$label, $color] = $e->statusBadge();

                return new HtmlString(Blade::render('<x-ui.badge :$color>{{ $label }}</x-ui.badge>', compact('label', 'color')));
            }),
            Column::make(__('Asked'), 'created_at')->sortable()->format(fn (LeaveEncashment $e) => format_date($e->created_at)),
            Column::make('', 'withdrawn_at')->format(fn (LeaveEncashment $e) => $e->isActive() && $e->paid_at === null
                ? new HtmlString(Blade::render(<<<'BLADE'
                    <flux:button size="xs" variant="ghost" class="!text-red-600" x-on:click="$dispatch('confirm', {
                        title: @js(__('Withdraw :no?', ['no' => $e->number()])),
                        message: @js($e->approved_at ? __('The days go back to the balance.') : __('The approvers are no longer asked.')),
                        confirmText: @js(__('Withdraw')),
                        onConfirm: () => $dispatch('withdraw-encashment', { id: {{ $e->id }} }),
                    })">{{ __('Withdraw') }}</flux:button>
                    BLADE, ['e' => $e]))
                : ''),
        ];
    }
}
