<?php

namespace App\Modules\Core\Livewire;

use App\Modules\Core\Models\FiscalYear;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * New / Edit fiscal year (modal "fiscal-year-form").
 * Opened by the "create-fiscal-year" or "edit-fiscal-year" browser events.
 */
class FiscalYearForm extends Component
{
    /** null = creating a new year */
    #[Locked]
    public ?int $yearId = null;

    public string $name = '';

    public string $startDate = '';

    public string $endDate = '';

    #[On('create-fiscal-year')]
    public function create(): void
    {
        $this->reset();
        $this->resetValidation();

        // Suggest the year right after the latest one (or one starting this month).
        $latest = FiscalYear::orderByDesc('end_date')->first();
        $start = $latest ? $latest->end_date->copy()->addDay() : now()->startOfMonth();
        $end = $start->copy()->addYear()->subDay();

        $this->startDate = $start->toDateString();
        $this->endDate = $end->toDateString();
        $this->name = $start->year === $end->year ? "FY {$start->year}" : "FY {$start->year}-{$end->format('y')}";

        Flux::modal('fiscal-year-form')->show();
    }

    #[On('edit-fiscal-year')]
    public function edit(int $id): void
    {
        $year = FiscalYear::findOrFail($id);

        $this->resetValidation();
        $this->yearId = $year->id;
        $this->name = $year->name;
        $this->startDate = $year->start_date->toDateString();
        $this->endDate = $year->end_date->toDateString();

        Flux::modal('fiscal-year-form')->show();
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique(FiscalYear::class, 'name')->ignore($this->yearId)],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after:startDate'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['startDate' => __('start date'), 'endDate' => __('end date')];
    }

    public function save(): void
    {
        $this->validate();

        $clash = FiscalYear::overlapping($this->startDate, $this->endDate)
            ->when($this->yearId, fn ($query) => $query->whereKeyNot($this->yearId))
            ->first();

        if ($clash) {
            $this->addError('startDate', __('These dates overlap :name (:start – :end).', [
                'name' => $clash->name,
                'start' => $clash->start_date->format('d M Y'),
                'end' => $clash->end_date->format('d M Y'),
            ]));

            return;
        }

        $year = FiscalYear::updateOrCreate(['id' => $this->yearId], [
            'name' => $this->name,
            'start_date' => Carbon::parse($this->startDate),
            'end_date' => Carbon::parse($this->endDate),
        ]);

        // A company must always have an active year: the first one becomes active by itself.
        if (FiscalYear::active() === null) {
            $year->activate();
        }

        Flux::modal('fiscal-year-form')->close();
        $this->dispatch('fiscal-year-saved');
        $this->dispatch('toast', message: __(':name saved.', ['name' => $year->name]), variant: 'success');
    }

    public function render(): View
    {
        return view('core::livewire.fiscal-year-form');
    }
}
