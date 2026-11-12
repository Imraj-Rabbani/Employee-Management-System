<?php

namespace App\Modules\Hrm\Livewire\Leave;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Hrm\Models\LeaveType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/** List of leave types, in their order. Click a name to edit it. [LV-01] */
class LeaveTypeTable extends DataTable
{
    /** @var array<string, string> profile counts change when a profile is saved */
    protected $listeners = ['leave-type-saved' => '$refresh', 'leave-profile-saved' => '$refresh'];

    /** @return Builder<LeaveType> */
    protected function query(): Builder
    {
        return LeaveType::query()->withCount('profileItems');
    }

    protected function defaultSort(): array
    {
        return ['sequence' => 'asc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Name'), 'name')->sortable()->searchable()->alwaysVisible()->opens('edit-leave-type'),
            Column::make(__('Code'), 'code')->sortable()->searchable(),
            Column::make(__('For'), 'gender')->format(fn (LeaveType $t) => collect([$t->gender?->label(), $t->religion?->label()])
                ->filter()->implode(' · ') ?: __('Everyone')),
            Column::make(__('Per application'), 'max_days')->format(fn (LeaveType $t) => $this->range($t)),
            Column::make(__('Earned'), 'earn_type')->sortable()->format(fn (LeaveType $t) => $t->earn_type->label()),
            Column::make(__('Rules'), 'is_lwp')->format(fn (LeaveType $t) => $this->badges($t)),
            Column::make(__('Profiles'), 'profile_items_count')->sortable()->alignRight(),
            Column::make(__('Order'), 'sequence')->sortable()->alignRight(),
            Column::active(),
        ];
    }

    /** "1–3 days", "up to 10 days", "at least 2 days" or "—". */
    private function range(LeaveType $type): string
    {
        $min = $type->min_days;
        $max = $type->max_days;

        return match (true) {
            $min !== null && $max !== null => __(':min–:max days', ['min' => $min, 'max' => $max]),
            $max !== null => __('up to :max days', ['max' => $max]),
            $min !== null => __('at least :min days', ['min' => $min]),
            default => '—',
        };
    }

    /** Small badges for the switches HR looks for most. */
    private function badges(LeaveType $type): HtmlString
    {
        $badges = array_filter([
            'warning' => $type->is_lwp ? __('Without pay') : null,
            'info' => $type->allow_half_day ? __('Half day') : null,
            'success' => $type->can_carry_forward ? __('Carry forward') : null,
            'neutral' => $type->is_encashable ? __('Encashable') : null,
            'danger' => $type->hr_only ? __('HR only') : null,
        ]);

        return new HtmlString(Blade::render(
            '<div class="flex flex-wrap gap-1">@foreach ($badges as $color => $text)<x-ui.badge :color="$color">{{ $text }}</x-ui.badge>@endforeach</div>',
            ['badges' => $badges],
        ));
    }
}
