<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Core\Livewire\Table\SelectFilter;
use App\Modules\Hrm\Enums\ManpowerRequestType;
use App\Modules\Hrm\Models\ManpowerRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * HRM → Recruitment → Manpower requests: the list. [REC-01]
 * Only requests of the org units the user can see (ManpowerRequest::visibleTo). Click the number to open its page (6.2c).
 */
class ManpowerRequestTable extends DataTable
{
    /** @var array<string, string> refresh the list when the form saved something */
    protected $listeners = ['manpower-request-saved' => '$refresh'];

    /** @return Builder<ManpowerRequest> */
    protected function query(): Builder
    {
        // 'approvals' eager loaded: the Status column calls approvalStatus() on every row.
        return ManpowerRequest::query()
            ->visibleTo(Auth::user())
            ->with(['designation', 'orgUnit', 'approvals']);
    }

    protected function defaultSort(): array
    {
        return ['id' => 'desc'];
    }

    protected function filters(): array
    {
        return [
            SelectFilter::make('type', __('Type'), ManpowerRequestType::options()),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('No.'), 'id')->sortable()->alwaysVisible()
                ->format(fn (ManpowerRequest $r) => new HtmlString(Blade::render(
                    '<a href="{{ route(\'hrm.recruitment.requests.show\', $r->id) }}" wire:navigate class="font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $r->number() }}</a>',
                    ['r' => $r],
                ))),
            Column::make(__('Designation'), 'designation_id')->alwaysVisible()->format(fn (ManpowerRequest $r) => $r->designation->name ?? '-'),
            Column::make(__('Org unit'), 'org_unit_id')->format(fn (ManpowerRequest $r) => $r->orgUnit->name ?? '-'),
            Column::make(__('Type'), 'type')->sortable()->format(fn (ManpowerRequest $r) => $r->type->label()),
            Column::make(__('Headcount'), 'headcount')->sortable()->alignRight(),
            Column::make(__('Needed by'), 'needed_by')->sortable()->format(fn (ManpowerRequest $r) => $r->needed_by ? format_date($r->needed_by) : '-'),
            Column::make(__('Status'), 'approved_at')
                ->format(fn (ManpowerRequest $r) => new HtmlString(Blade::render(
                    '@if ($status) <x-ui.badge :color="$status->color()">{{ $status->label() }}</x-ui.badge> @else <x-ui.badge>{{ __(\'Draft\') }}</x-ui.badge> @endif',
                    ['status' => $r->approvalStatus()],
                ))),
            Column::make(__('Created'), 'created_at')->sortable()->format(fn (ManpowerRequest $r) => format_date($r->created_at)),
        ];
    }
}
