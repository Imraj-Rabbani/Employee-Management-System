<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Core\Livewire\Table\SelectFilter;
use App\Modules\Hrm\Enums\JobPostingStatus;
use App\Modules\Hrm\Models\JobPosting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * HRM → Job Postings: the list. [REC-02]
 * Only postings of org units the user can see (JobPosting::visibleTo). Click the number to open its page.
 */
class JobPostingTable extends DataTable
{
    /** @var array<string, string> refresh the list when the form saved something */
    protected $listeners = ['job-posting-saved' => '$refresh'];

    /** @return Builder<JobPosting> */
    protected function query(): Builder
    {
        // "manpowerRequest.orgUnit": nested eager loading, the request AND its org unit in 2 queries.
        return JobPosting::query()
            ->visibleTo(Auth::user())
            ->with(['manpowerRequest.orgUnit']);
    }

    protected function defaultSort(): array
    {
        return ['id' => 'desc'];
    }

    protected function filters(): array
    {
        return [
            SelectFilter::make('status', __('Status'), JobPostingStatus::options()),
        ];
    }

    /** A column's field is also its key (column chooser), so "Org unit" gets its own name: it is not a real column. */
    protected function columns(): array
    {
        return [
            Column::make(__('No.'), 'id')->sortable()->alwaysVisible()
                ->format(fn (JobPosting $p) => new HtmlString(Blade::render(
                    '<a href="{{ route(\'hrm.recruitment.postings.show\', $p->id) }}" wire:navigate class="font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $p->number() }}</a>',
                    ['p' => $p],
                ))),
            Column::make(__('Title'), 'title')->sortable()->searchable()->alwaysVisible(),
            Column::make(__('Org unit'), 'org_unit')->format(fn (JobPosting $p) => $p->manpowerRequest->orgUnit->name ?? '-'),
            Column::make(__('Request'), 'manpower_request_id')
                ->format(fn (JobPosting $p) => new HtmlString(Blade::render(
                    '<a href="{{ route(\'hrm.recruitment.requests.show\', $r->id) }}" wire:navigate class="text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $r->number() }}</a>',
                    ['r' => $p->manpowerRequest],
                ))),
            Column::make(__('Openings'), 'openings')->sortable()->alignRight(),
            Column::make(__('Opens'), 'open_date')->sortable()->format(fn (JobPosting $p) => format_date($p->open_date)),
            Column::make(__('Closes'), 'close_date')->sortable()->format(fn (JobPosting $p) => $p->close_date ? format_date($p->close_date) : __('Until closed')),
            Column::make(__('Status'), 'status')->sortable()
                ->format(fn (JobPosting $p) => new HtmlString(Blade::render(
                    '<x-ui.badge :color="$s->color()">{{ $s->label() }}</x-ui.badge>',
                    ['s' => $p->status],
                ))),
        ];
    }
}
