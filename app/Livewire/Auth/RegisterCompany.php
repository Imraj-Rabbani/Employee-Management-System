<?php

namespace App\Livewire\Auth;

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
 *   - the company, with its own database and every module switched on; its code (used in the
 *     database name) is made from the organisation name, so the person never has to think of one
 *   - their login, with the company's Administrator role (every screen of THIS company only;
 *     "super admin" in this app is the platform owner, which a sign-up never becomes)
 *   - their employee record (Employee ID they chose), so they appear in HRM → Employees
 * Then they are logged in and land on the dashboard. From Employees they can invite others.
 *
 * Each sign-up creates a whole database, so one IP address may sign up at most 3 times an hour.
 */
class RegisterCompany extends Component
{
    /** Sign-ups allowed per IP address per hour. */
    public const MAX_PER_HOUR = 3;

    public string $organisation = '';

    public string $name = '';

    public string $email = '';

    public string $employeeCode = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'organisation' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
            // The company's database is still empty, so any Employee ID is free.
            'employeeCode' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', Password::min(8)],
            'passwordConfirmation' => ['required', 'same:password'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'organisation' => __('organisation name'),
            'employeeCode' => __('employee ID'),
            'passwordConfirmation' => __('password confirmation'),
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
                adminName: $this->name,
                adminEmail: $this->email,
                adminPassword: $this->password,
                modules: array_keys(Tenant::OPTIONAL_MODULES),
                then: fn (Tenant $tenant, User $admin) => $createEmployee->handle($admin, [
                    'code' => $this->employeeCode,
                    ...CreateEmployeeForUser::splitName($this->name),
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
        return view('livewire.auth.register-company');
    }
}
