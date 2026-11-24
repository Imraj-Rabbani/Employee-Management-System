<?php

namespace App\Livewire\Auth;

use App\Core\Enums\Plan;
use App\Core\Tenancy\CreateTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Hrm\Actions\CreateEmployeeForUser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

/**
 * Public sign-up page (/register): someone registers their organisation and starts using it right away.
 *
 * One click does it all (CreateTenant action):
 *   - the company, on the plan they picked (Free / Business), with its own database and every module switched on; its code (used in the
 *     database name) is made from the organisation name, so the person never has to think of one
 *   - their login, with the company's Administrator role (every screen of THIS company only;
 *     "super admin" in this app is the platform owner, which a sign-up never becomes)
 *   - their employee record (Employee ID EMP-001, changeable later), so they appear in HRM → Employees
 * Then they are logged in and land on the dashboard. From Employees they can invite others.
 *
 * Each sign-up creates a whole database, so one IP address may sign up at most 3 times an hour.
 */
class RegisterCompany extends Component
{
    /** Sign-ups allowed per IP address per hour. */
    public const MAX_PER_HOUR = 3;

    /** The founder's Employee ID. Not asked on the form (kept short); they can change it on their profile. */
    public const FIRST_EMPLOYEE_CODE = 'EMP-001';

    /** "free" or "business" (App\Core\Enums\Plan). The pricing page links here with ?plan=business. */
    public string $plan = 'free';

    public string $organisation = '';

    public string $firstName = '';

    /** Optional: some people have only one name. */
    public string $lastName = '';

    public string $email = '';

    public string $password = '';

    public function mount(): void
    {
        $this->plan = Plan::tryFrom((string) request()->query('plan'))?->value ?? Plan::Free->value;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'plan' => ['required', Rule::enum(Plan::class)],
            'organisation' => ['required', 'string', 'max:255'],
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', Password::min(8)],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'organisation' => __('organisation name'),
            'firstName' => __('first name'),
            'lastName' => __('last name'),
        ];
    }

    public function register(CreateTenant $createTenant, CreateEmployeeForUser $createEmployee): void
    {
        $this->validate();

        $throttleKey = 'register-company:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_PER_HOUR)) {
            throw ValidationException::withMessages([
                'organisation' => __('Too many sign-ups from your network. Please try again in :minutes minutes.', [
                    'minutes' => (int) ceil(RateLimiter::availableIn($throttleKey) / 60),
                ]),
            ]);
        }

        try {
            $tenant = $createTenant->handle(
                code: $createTenant->codeFor($this->organisation),
                name: $this->organisation,
                adminName: trim($this->firstName.' '.$this->lastName),
                adminEmail: $this->email,
                adminPassword: $this->password,
                modules: array_keys(Tenant::OPTIONAL_MODULES),
                plan: Plan::from($this->plan),
                then: fn (Tenant $tenant, User $admin) => $createEmployee->handle($admin, [
                    'code' => self::FIRST_EMPLOYEE_CODE,
                    'first_name' => trim($this->firstName),
                    'last_name' => trim($this->lastName),
                ]),
            );
        } catch (RuntimeException $e) {
            // Everything was undone. Don't show database details to the public.
            report($e);
            $this->addError('organisation', __('Your organisation could not be registered. Please try again.'));

            return;
        }

        RateLimiter::hit($throttleKey, 3600);

        Auth::login($tenant->users()->sole());
        session()->regenerate();
        session()->flash('toast', ['message' => __('Welcome! :name is ready.', ['name' => $tenant->name]), 'variant' => 'success']);

        $this->redirect(route('home'));
    }

    public function render(): View
    {
        return view('livewire.auth.register-company', ['plans' => Plan::cases()]);
    }
}
