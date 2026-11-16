<?php

namespace App\Modules\Payroll\Livewire;

use App\Modules\Payroll\Enums\HeadRole;
use App\Modules\Payroll\Models\PayScaleProfile;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Payroll\Services\PayrollSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Payroll → Salary Setup → Settings: which head does which job for the payroll run (head mapping, legacy headTax,
 * headPF ...) and the default pay scale profile.
 *
 * A head must be of the role's type (tax: deduction, overtime: earning ...), not the basic, and do one job only.
 */
class PayrollSettingsForm extends Component
{
    /** @var array<string, string> role value => salary_head_id ('' = not set) */
    public array $heads = [];

    public string $defaultPayScale = '';

    public function mount(PayrollSettings $settings): void
    {
        $ids = $settings->headIds();
        foreach (HeadRole::cases() as $role) {
            $this->heads[$role->value] = (string) ($ids[$role->value] ?? '');
        }
        $this->defaultPayScale = (string) ($settings->defaultPayScale()->id ?? '');
    }

    public function save(): void
    {
        Gate::authorize('payroll.salary.manage');
        $this->validate([
            'heads' => ['array'],
            'heads.*' => ['nullable', Rule::exists(SalaryHead::class, 'id')],
            'defaultPayScale' => ['nullable', Rule::exists(PayScaleProfile::class, 'id')],
        ], attributes: ['heads.*' => __('head'), 'defaultPayScale' => __('default pay scale profile')]);

        $chosen = array_filter(
            array_intersect_key($this->heads, array_flip(array_column(HeadRole::cases(), 'value'))),
            fn (string $id) => $id !== '',
        );
        $heads = SalaryHead::findMany($chosen)->keyBy('id');

        foreach ($chosen as $role => $id) {
            $role = HeadRole::from($role);
            $head = $heads[(int) $id];
            if ($head->is_basic) {
                $this->addError("heads.{$role->value}", __('The basic salary cannot do another job.'));
            } elseif ($head->type !== $role->headType()) {
                $this->addError("heads.{$role->value}", __(':role needs a head of type :type.', ['role' => $role->label(), 'type' => $role->headType()->label()]));
            }
        }
        foreach (array_count_values(array_map('strval', $chosen)) as $id => $times) {
            if ($times > 1) {
                foreach (array_keys($chosen, $id) as $role) {
                    $this->addError("heads.{$role}", __(':head is chosen for more than one job.', ['head' => $heads[(int) $id]->name]));
                }
            }
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        settings()->setMany([
            PayrollSettings::HEADS => array_map('intval', $chosen),
            PayrollSettings::DEFAULT_PAY_SCALE => $this->defaultPayScale !== '' ? (int) $this->defaultPayScale : null,
        ]);
        $this->dispatch('toast', message: __('Payroll settings saved.'), variant: 'success');
    }

    public function render(): View
    {
        // Per role: the heads of the right type (inactive ones only when already chosen), never the basic.
        $heads = SalaryHead::where('is_basic', false)->ordered()->get();
        $options = [];
        foreach (HeadRole::cases() as $role) {
            $options[$role->value] = $heads
                ->filter(fn (SalaryHead $h) => $h->type === $role->headType() && ($h->is_active || (string) $h->id === ($this->heads[$role->value] ?? '')))
                ->mapWithKeys(fn (SalaryHead $h) => [$h->id => "{$h->name} ({$h->code})"])->all();
        }

        return view('payroll::livewire.payroll-settings-form', [
            'roles' => HeadRole::cases(),
            'options' => $options,
            'payScales' => PayScaleProfile::where('is_active', true)->orWhere('id', (int) ($this->defaultPayScale ?: 0))->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
