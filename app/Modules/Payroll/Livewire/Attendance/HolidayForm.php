<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Livewire\LookupForm;
use App\Core\Models\TenantModel;
use App\Modules\Payroll\Models\Holiday;
use App\Modules\Payroll\Models\HolidayProfile;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete holiday (modal "holiday-form"). [TA-02]
 * Also picks the holiday calendars it belongs to (pivot holiday_holiday_profile): $profileIds.
 */
class HolidayForm extends LookupForm
{
    /** @var list<string> ids of the ticked calendars (checkbox values arrive as text) */
    public array $profileIds = [];

    protected function key(): string
    {
        return 'holiday';
    }

    protected function model(): string
    {
        return Holiday::class;
    }

    protected function defaults(): array
    {
        return ['date' => '', 'title' => '', 'is_public' => true, 'is_festival' => false, 'repeats_yearly' => false, 'detail' => ''];
    }

    /** A new holiday is in every active calendar unless unticked: that is what HR wants most of the time. */
    public function create(array $preset = []): void
    {
        parent::create($preset);
        $this->profileIds = HolidayProfile::where('is_active', true)->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function edit(int $id): void
    {
        parent::edit($id);
        $this->profileIds = Holiday::findOrFail($id)->profiles()->pluck('holiday_profiles.id')->map(fn ($id) => (string) $id)->all();
    }

    protected function fieldRules(): array
    {
        return [
            'date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:100',
                Rule::unique(Holiday::class, 'title')->where('date', $this->form['date'] ?? null)->ignore($this->recordId)],
            'is_public' => ['boolean'],
            'is_festival' => ['boolean'],
            'repeats_yearly' => ['boolean'],
            'detail' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function rules(): array
    {
        return [
            ...parent::rules(),
            'profileIds' => ['array'],
            'profileIds.*' => ['integer', Rule::exists(HolidayProfile::class, 'id')],
        ];
    }

    protected function labels(): array
    {
        return ['repeats_yearly' => __('every year')];
    }

    /** @param  Holiday  $record */
    protected function saved(TenantModel $record): void
    {
        $record->profiles()->sync(array_map('intval', $this->profileIds));
    }

    protected function title(TenantModel $record): string
    {
        return (string) $record->getAttribute('title');
    }

    protected function viewData(): array
    {
        return ['profiles' => HolidayProfile::orderBy('name')->get(['id', 'name', 'is_active'])];
    }

    protected function view(): string
    {
        return 'payroll::livewire.attendance.holiday-form';
    }
}
