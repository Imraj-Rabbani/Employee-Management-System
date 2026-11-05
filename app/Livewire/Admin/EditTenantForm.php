<?php

namespace App\Livewire\Admin;

use App\Core\Plugins\PluginRegistry;
use App\Models\Tenant;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The "Edit company" form (modal "edit-tenant") in Platform admin.
 * Opened by clicking a company code in the list, which sends the "edit-tenant" event.
 *
 * The code and database cannot be changed: they name the company's database and files folder.
 */
class EditTenantForm extends Component
{
    /** #[Locked]: the browser cannot change this value, so nobody can swap in another company's id. */
    #[Locked]
    public ?int $tenantId = null;

    public string $code = '';

    public string $name = '';

    public bool $isActive = true;

    /** "YYYY-MM-DD" or "" for no expiry */
    public string $expiresAt = '';

    /** @var list<string> */
    public array $modules = [];

    /** @var array<string, mixed> slot => plugin key (or list of keys), e.g. ['tax' => 'TaxBangladesh'] */
    public array $plugins = [];

    /** Runs on every request, like in CreateTenantForm. */
    public function boot(): void
    {
        Gate::authorize('super-admin');
    }

    #[On('edit-tenant')]
    public function edit(int $id): void
    {
        $tenant = Tenant::findOrFail($id);

        $this->tenantId = $tenant->id;
        $this->code = $tenant->code;
        $this->name = $tenant->name;
        $this->isActive = $tenant->is_active;
        $this->expiresAt = $tenant->expires_at?->toDateString() ?? '';
        $this->modules = $tenant->modules ?? [];
        $this->plugins = $tenant->plugins ?? [];

        $this->resetValidation();
        Flux::modal('edit-tenant')->show();
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'isActive' => ['boolean'],
            'expiresAt' => ['nullable', 'date'],
            'modules' => ['array'],
            'modules.*' => [Rule::in(array_keys(Tenant::OPTIONAL_MODULES))],
            ...app(PluginRegistry::class)->rules('plugins'),
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['expiresAt' => __('expiry date')];
    }

    public function save(): void
    {
        $this->validate();

        $tenant = Tenant::findOrFail($this->tenantId);
        $tenant->update([
            'name' => $this->name,
            'is_active' => $this->isActive,
            'expires_at' => $this->expiresAt !== '' ? $this->expiresAt : null,
            'modules' => $this->modules,
            'plugins' => app(PluginRegistry::class)->clean($this->plugins),
        ]);

        Flux::modal('edit-tenant')->close();
        $this->dispatch('tenant-saved');
        $this->dispatch('toast', message: __(':name saved', ['name' => $tenant->name]), variant: 'success');
    }

    public function render(): View
    {
        $registry = app(PluginRegistry::class);

        return view('livewire.admin.edit-tenant-form', [
            'moduleOptions' => Tenant::OPTIONAL_MODULES,
            'pluginSlots' => collect($registry->slots())
                ->map(fn (array $slot, string $key) => [...$slot, 'options' => $registry->options($key)])
                ->all(),
        ]);
    }
}
