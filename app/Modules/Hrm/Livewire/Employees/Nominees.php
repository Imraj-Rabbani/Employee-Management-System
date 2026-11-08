<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Enums\EmployeeRelation;
use App\Modules\Hrm\Models\EmployeeNominee;
use Illuminate\Validation\Rule;

/**
 * Profile → Family & Nominees → "Nominees" card. [EMP-02]
 *
 * Shares: a save may not bring the total ABOVE 100%. Below 100% is allowed while nominees are being
 * added one by one; the card then shows a warning until the total is exactly 100%.
 * Percentages are compared in hundredths (33.34% → 3334) as whole numbers, so no float rounding.
 */
class Nominees extends EmployeeRecords
{
    protected function relation(): string
    {
        return 'nominees';
    }

    protected function defaults(): array
    {
        return [
            'name' => '', 'relation' => '', 'national_id' => '', 'phone' => '', 'address' => '', 'share_percent' => '',
            'is_minor' => false, 'guardian_name' => '', 'guardian_national_id' => '', 'guardian_phone' => '', 'guardian_relation' => '',
        ];
    }

    protected function fieldRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'relation' => ['nullable', Rule::enum(EmployeeRelation::class)],
            'national_id' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'share_percent' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:100'],
            'is_minor' => ['boolean'],
            'guardian_name' => ['nullable', 'required_if_accepted:form.is_minor', 'string', 'max:200'],
            'guardian_national_id' => ['nullable', 'string', 'max:30'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'guardian_relation' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function labels(): array
    {
        return [
            'share_percent' => __('share'), 'national_id' => __('national ID'), 'guardian_name' => __('guardian name'),
            'guardian_national_id' => __('guardian national ID'), 'guardian_phone' => __('guardian phone'),
        ];
    }

    protected function check(array $values): bool
    {
        $others = $this->employee()->nominees()->whereKeyNot($this->recordId ?? 0)->get()
            ->sum(fn (EmployeeNominee $n) => self::hundredths($n->share_percent));
        $total = $others + self::hundredths((string) $values['share_percent']);

        if ($total > 10000) {
            $this->addError('form.share_percent', __('All shares together would be :total%. At most :left% is left.', [
                'total' => self::percent($total), 'left' => self::percent(10000 - $others),
            ]));

            return false;
        }

        return true;
    }

    /** The guardian columns are one JSON value; only kept for a minor. */
    protected function fromForm(array $values): array
    {
        $guardian = [
            'name' => $values['guardian_name'], 'national_id' => $values['guardian_national_id'],
            'phone' => $values['guardian_phone'], 'relation' => $values['guardian_relation'],
        ];
        unset($values['guardian_name'], $values['guardian_national_id'], $values['guardian_phone'], $values['guardian_relation']);

        $values['is_minor'] = (bool) $values['is_minor'];
        $values['guardian'] = $values['is_minor'] ? $guardian : null;

        return $values;
    }

    protected function toForm(TenantModel $record): array
    {
        /** @var EmployeeNominee $record */
        $form = parent::toForm($record);
        foreach (['name', 'national_id', 'phone', 'relation'] as $key) {
            $form['guardian_'.$key] = $record->guardian[$key] ?? '';
        }

        return $form;
    }

    /** "Copy from family member": fills name and relation from one of the employee's family members. */
    public function copyFamily(int $id): void
    {
        $member = $this->employee(update: true)->familyMembers()->findOrFail($id);

        $this->form['name'] = $member->name;
        $this->form['relation'] = $member->relation->value ?? '';
    }

    protected function viewData(): array
    {
        $employee = $this->employee();
        $total = $employee->nominees()->get()->sum(fn (EmployeeNominee $n) => self::hundredths($n->share_percent));

        return [
            'relations' => EmployeeRelation::options(),
            'family' => $employee->familyMembers()->get()->mapWithKeys(fn ($m) => [$m->id => $m->name.($m->relation ? ' · '.$m->relation->label() : '')])->all(),
            'total' => self::percent($total),
            'complete' => $total === 10000,
        ];
    }

    /** "33.34" → 3334 */
    private static function hundredths(string $percent): int
    {
        return (int) round((float) $percent * 100);
    }

    /** 3334 → "33.34", 10000 → "100" */
    private static function percent(int $hundredths): string
    {
        return rtrim(rtrim(number_format($hundredths / 100, 2, '.', ''), '0'), '.');
    }

    protected function view(): string
    {
        return 'hrm::livewire.employees.nominees';
    }
}
