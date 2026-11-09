<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Hrm\Models\Applicant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * HRM → Applicants: the talent pool. Search by name, email or phone. [REC-03]
 * Phones are stored as digits only (01711000000), so search a phone without spaces or dashes.
 */
class ApplicantTable extends DataTable
{
    /** @return Builder<Applicant> */
    protected function query(): Builder
    {
        // withCount: one extra column "applications_count" in the same query, no query per row.
        return Applicant::query()->withCount('applications');
    }

    protected function defaultSort(): array
    {
        return ['id' => 'desc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Name'), 'first_name')->sortable()->searchable('last_name', 'email', 'phone')->alwaysVisible()
                ->format(fn (Applicant $a) => new HtmlString(Blade::render(
                    '<a href="{{ route(\'hrm.recruitment.applicants.show\', $a) }}" wire:navigate class="font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $a->name }}</a>',
                    ['a' => $a],
                ))),
            Column::make(__('Email'), 'email'),
            Column::make(__('Phone'), 'phone'),
            Column::make(__('Current employer'), 'current_employer'),
            Column::make(__('Applications'), 'applications_count')->sortable()->alignRight(),
            Column::make(__('CV'), 'cv_path')->format(fn (Applicant $a) => $a->cv_path ? __('Yes') : ''),
            Column::make(__('Added'), 'created_at')->sortable()->format(fn (Applicant $a) => format_date($a->created_at)),
        ];
    }
}
