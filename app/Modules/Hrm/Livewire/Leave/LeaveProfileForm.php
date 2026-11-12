<?php

namespace App\Modules\Hrm\Livewire\Leave;

use App\Core\Livewire\LookupForm;
use App\Core\Models\TenantModel;
use App\Modules\Hrm\Models\LeaveProfile;
use App\Modules\Hrm\Models\LeaveType;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete leave profile (modal "leave-profile-form"), with its days per year per leave type. [LV-02]
 *
 * $days = [leave_type_id => days]. Empty = the type is NOT in the profile (cannot be taken);
 * 0 = in the profile without days (for leave that needs no balance, e.g. without pay).
 */
class LeaveProfileForm extends LookupForm
{
    /** @var array<int, string|float|int|null> leave_type_id => days per year ('' = not in the profile) */
    public array $days = [];

    protected function key(): string
    {
        return 'leave-profile';
    }

    protected function model(): string
    {
        return LeaveProfile::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'detail' => '', 'is_active' => true];
    }

    public function create(array $preset = []): void
    {
        parent::create($preset);
        $this->days = [];
    }

    public function edit(int $id): void
    {
        parent::edit($id);
        $this->days = LeaveProfile::findOrFail($id)->entitlements();
    }

    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique(LeaveProfile::class, 'name')->ignore($this->recordId)],
            'detail' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    protected function rules(): array
    {
        return [
            ...parent::rules(),
            'days' => ['array'],
            'days.*' => ['nullable', 'numeric', 'min:0', 'max:366', 'multiple_of:0.5'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [...parent::validationAttributes(), 'days.*' => __('days')];
    }

    /** @param  LeaveProfile  $record */
    protected function saved(TenantModel $record): void
    {
        $filled = array_filter($this->days, fn ($value) => $value !== '' && $value !== null);
        // Only real leave types (the browser could send any key).
        $known = LeaveType::whereIn('id', array_keys($filled))->pluck('id')->all();

        $record->syncEntitlements(collect($filled)->only($known)->map(fn ($value) => (float) $value)->all());
    }

    protected function viewData(): array
    {
        // Active types, plus inactive ones still in this profile (or they would silently drop out on save).
        return [
            'types' => LeaveType::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', array_keys($this->days)))
                ->ordered()->get(),
        ];
    }

    protected function view(): string
    {
        return 'hrm::livewire.leave.leave-profile-form';
    }
}
