<?php

namespace App\Livewire\Admin;

use App\Core\Tenancy\CreateTenant;
use App\Models\Tenant;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use RuntimeException;

/**
 * The "New company" form (modal "create-tenant") in Platform admin.
 * Validates the input, then hands the work to the CreateTenant action (task 2.6b).
 */
class CreateTenantForm extends Component
{
    public string $code = '';

    public string $name = '';

    /** @var list<string> */
    public array $modules = ['hrm', 'payroll'];

    public string $adminName = '';

    public string $adminEmail = '';

    public string $adminPassword = '';

    /**
     * Runs on the first load AND on every later Livewire request (/livewire/update),
     * so nobody but a super admin can use this component, even by crafting requests.
     */
    public function boot(): void
    {
        Gate::authorize('super-admin');
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            // Becomes part of the database name and the files folder, so keep it simple.
            'code' => ['required', 'string', 'max:30', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique(Tenant::class, 'code')],
            'name' => ['required', 'string', 'max:255'],
            'modules' => ['array'],
            'modules.*' => [Rule::in(array_keys(Tenant::OPTIONAL_MODULES))],
            'adminName' => ['required', 'string', 'max:255'],
            'adminEmail' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'adminPassword' => ['required', 'string', Password::min(8)],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'code.regex' => __('Use lowercase letters, numbers and _ only, starting with a letter.'),
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'adminName' => __('admin name'),
            'adminEmail' => __('admin email'),
            'adminPassword' => __('admin password'),
        ];
    }

    /** Typing "SSS " in the code field becomes "sss". */
    public function updatedCode(string $value): void
    {
        $this->code = strtolower(trim($value));
    }

    public function save(CreateTenant $createTenant): void
    {
        $this->validate();

        try {
            $tenant = $createTenant->handle(
                code: $this->code,
                name: $this->name,
                adminName: $this->adminName,
                adminEmail: $this->adminEmail,
                adminPassword: $this->adminPassword,
                modules: $this->modules,
            );
        } catch (RuntimeException $e) {
            // e.g. "Database [ems_tenant_x] already exists". Everything was undone; show it on the form.
            $this->addError('code', $e->getMessage());

            return;
        }

        $this->reset();
        Flux::modal('create-tenant')->close();
        $this->dispatch('tenant-saved');
        $this->dispatch('toast', message: __(':name created', ['name' => $tenant->name]), variant: 'success');
    }

    public function render(): View
    {
        return view('livewire.admin.create-tenant-form', [
            'moduleOptions' => Tenant::OPTIONAL_MODULES,
        ]);
    }
}
