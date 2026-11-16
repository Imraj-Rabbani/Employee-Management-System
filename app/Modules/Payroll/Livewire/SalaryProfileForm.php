<?php

namespace App\Modules\Payroll\Livewire;

use App\Core\Livewire\LookupForm;
use App\Core\Models\TenantModel;
use App\Core\Support\Money;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Payroll\Data\SalaryBreakdown;
use App\Modules\Payroll\Enums\CalcMethod;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Enums\ProfileValueType;
use App\Modules\Payroll\Enums\SalaryBasis;
use App\Modules\Payroll\Exceptions\SalaryProfileException;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Payroll\Models\SalaryProfile;
use App\Modules\Payroll\Models\SalaryProfileItem;
use App\Modules\Payroll\Services\SalaryProfileCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete salary profile (modal "salary-profile-form"), with the value of each head.
 *
 * $items = [salary_head_id => ['value_type' => 'percent_basic', 'value' => '50']]. Empty value = the head is NOT in
 * the profile. The basic head is never an item (it is the base), nor are formula / per-attendance-day heads (the
 * payroll run works those out).
 *
 * "Apply profile" preview: type a basic or a gross and see every head's amount, from the values typed (not saved).
 */
class SalaryProfileForm extends LookupForm
{
    /** @var array<int, array{value_type?: string, value?: string|int|float|null}> as sent by the browser */
    public array $items = [];

    public string $previewBasis = 'gross';

    public string $previewAmount = '30000';

    protected function key(): string
    {
        return 'salary-profile';
    }

    protected function model(): string
    {
        return SalaryProfile::class;
    }

    protected function defaults(): array
    {
        return ['code' => '', 'name' => '', 'grade_id' => '', 'detail' => '', 'is_active' => true];
    }

    public function create(array $preset = []): void
    {
        parent::create($preset);
        $this->items = $this->blankItems();
    }

    public function edit(int $id): void
    {
        parent::edit($id);
        $this->items = array_replace($this->blankItems(), SalaryProfile::findOrFail($id)->values());
    }

    protected function fieldRules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique(SalaryProfile::class, 'code')->ignore($this->recordId)],
            'name' => ['required', 'string', 'max:100', Rule::unique(SalaryProfile::class, 'name')->ignore($this->recordId)],
            'grade_id' => ['nullable', Rule::exists(Grade::class, 'id')],
            'detail' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    protected function rules(): array
    {
        return [
            ...parent::rules(),
            'items' => ['array'],
            'items.*.value_type' => ['required', Rule::enum(ProfileValueType::class)],
            'items.*.value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [...parent::validationAttributes(), 'items.*.value' => __('value'), 'form.grade_id' => __('grade')];
    }

    /** Percents up to 1000, and the "% of gross" earnings must leave room for the basic. */
    protected function check(array $values): bool
    {
        $heads = $this->heads()->keyBy('id');
        $ofGross = 0.0;
        $ok = true;

        foreach ($this->filled() as $headId => $item) {
            $type = ProfileValueType::from($item['value_type']);
            if ($type->usesRate() && (float) $item['value'] > 1000) {
                $this->addError("items.{$headId}.value", __('A percent cannot be more than 1000.'));
                $ok = false;
            }
            $head = $heads[$headId] ?? null;
            if ($type === ProfileValueType::PercentOfGross && $head?->type === HeadType::Earning && $head->in_gross) {
                $ofGross += (float) $item['value'];
            }
        }

        if ($ofGross >= 100) {
            $this->addError('items', __('The earnings set as % of gross add up to 100% or more, so there is no room for the basic.'));
            $ok = false;
        }

        return $ok;
    }

    /** @param  SalaryProfile  $record */
    protected function saved(TenantModel $record): void
    {
        $record->syncItems(array_map(fn (array $i) => ['value_type' => $i['value_type'], 'value' => $i['value']], $this->filled()));
    }

    protected function view(): string
    {
        return 'payroll::livewire.salary-profile-form';
    }

    protected function viewData(): array
    {
        [$breakdown, $problem] = $this->preview();

        return [
            'heads' => $this->heads(),
            'basicHead' => SalaryHead::basic(),
            'grades' => Grade::where('is_active', true)->orWhere('id', $this->form['grade_id'] ?? 0)->ordered()->pluck('name', 'id')->all(),
            'valueTypes' => ProfileValueType::options(),
            'bases' => SalaryBasis::options(),
            'breakdown' => $breakdown,
            'problem' => $problem,
        ];
    }

    /**
     * Heads that can be in a profile: not the basic, not formula / per-attendance-day; active, or already in this
     * profile (or they would silently drop out on save).
     *
     * @return Collection<int, SalaryHead>
     */
    private function heads(): Collection
    {
        $inProfile = $this->recordId ? SalaryProfileItem::where('salary_profile_id', $this->recordId)->pluck('salary_head_id')->all() : [];

        return SalaryHead::query()
            ->where('is_basic', false)
            ->whereNotIn('calc_method', [CalcMethod::Formula, CalcMethod::Attendance])
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $inProfile))
            ->ordered()->get();
    }

    /** @return array<int, array{value_type: string, value: string}> every head, value empty, type = the head's own rule */
    private function blankItems(): array
    {
        return $this->heads()->mapWithKeys(fn (SalaryHead $h) => [
            $h->id => ['value_type' => ProfileValueType::fromCalcMethod($h->calc_method)->value, 'value' => ''],
        ])->all();
    }

    /**
     * The items with a value, only for heads allowed in a profile (the browser could send any key).
     *
     * @return array<int, array{value_type: string, value: string|int|float}>
     */
    private function filled(): array
    {
        $allowed = $this->heads()->modelKeys();
        $filled = [];
        foreach ($this->items as $headId => $item) {
            $type = ProfileValueType::tryFrom((string) ($item['value_type'] ?? ''));
            $value = $item['value'] ?? null;
            if ($type !== null && is_numeric($value) && in_array($headId, $allowed, true)) {
                $filled[$headId] = ['value_type' => $type->value, 'value' => $value];
            }
        }

        return $filled;
    }

    /**
     * The profile as typed, applied to the sample basic / gross. Half-typed input never breaks it.
     *
     * @return array{0: ?SalaryBreakdown, 1: ?string} breakdown, or the reason there is none
     */
    private function preview(): array
    {
        $basicHead = SalaryHead::basic();
        $basis = SalaryBasis::tryFrom($this->previewBasis);
        if ($basicHead === null || $basis === null || ! is_numeric(trim($this->previewAmount))) {
            return [null, $basicHead === null ? __('Mark one salary head as the basic salary first (Salary heads tab).') : null];
        }

        $heads = $this->heads()->keyBy('id');
        $items = [];
        foreach ($this->filled() as $headId => $value) {
            $type = ProfileValueType::from($value['value_type']);
            $item = new SalaryProfileItem([
                'value_type' => $type,
                'amount' => $type->usesRate() ? null : (string) $value['value'],
                'rate' => $type->usesRate() ? (string) $value['value'] : null,
            ]);
            $items[] = $item->setRelation('head', $heads[$headId]);
        }

        try {
            return [app(SalaryProfileCalculator::class)->apply($basicHead, $items, $basis, Money::of(trim($this->previewAmount))), null];
        } catch (SalaryProfileException $e) {
            return [null, $e->getMessage()];
        }
    }
}
