<?php

namespace App\Modules\Hrm\Actions;

use App\Models\User;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * A person who registers themselves becomes an employee of the company, linked to their login:
 *   - the company founder on the sign-up page (/register)
 *   - an invited user on the "complete your registration" page (/join/...)
 *
 *     app(CreateEmployeeForUser::class)->handle($user, ['code' => 'EMP-001', 'first_name' => 'Rahim', ...]);
 *
 * Must run inside the user's company (its database is the current one). Org unit, designation etc.
 * stay empty: HR fills them in later on the employee's profile. The join date is today.
 */
class CreateEmployeeForUser
{
    /**
     * Rules for the fields a person may fill in about themselves. Use inside the user's company.
     *
     * @return array<string, mixed>
     */
    public static function rules(string $prefix = ''): array
    {
        return [
            $prefix.'code' => ['required', 'string', 'max:50', Rule::unique(Employee::class, 'code')],
            $prefix.'first_name' => ['required', 'string', 'max:100'],
            $prefix.'last_name' => ['nullable', 'string', 'max:100'],
            $prefix.'mobile' => ['nullable', 'string', 'max:30'],
            $prefix.'gender' => ['nullable', Rule::enum(Gender::class)],
            $prefix.'birth_date' => ['nullable', 'date', 'before:today'],
        ];
    }

    /**
     * "Md. Rahim Uddin" → first name "Md. Rahim", last name "Uddin" (one word: first name only).
     *
     * @return array{first_name: string, last_name: string|null}
     */
    public static function splitName(string $fullName): array
    {
        $fullName = trim((string) preg_replace('/\s+/', ' ', $fullName));
        $last = strrpos($fullName, ' ');

        return $last === false
            ? ['first_name' => $fullName, 'last_name' => null]
            : ['first_name' => substr($fullName, 0, $last), 'last_name' => substr($fullName, $last + 1)];
    }

    /** @param  array<string, mixed>  $details  code, first_name, and optionally last_name, mobile, gender, birth_date */
    public function handle(User $user, array $details): Employee
    {
        if (tenant() === null || $user->tenant_id !== tenant()->id) {
            throw new LogicException('Create the employee inside the user\'s own company.');
        }

        if (Employee::withoutGlobalScope(VisibleToCurrentUser::class)->where('user_id', $user->id)->exists()) {
            throw new LogicException('This login already belongs to an employee.');
        }

        $values = array_map(
            fn ($v) => $v === '' ? null : $v,
            array_intersect_key($details, array_flip(['code', 'first_name', 'last_name', 'mobile', 'gender', 'birth_date'])),
        );

        $employee = Employee::create([
            ...$values,
            'office_email' => $user->email,
            'join_date' => now()->toDateString(),
        ]);
        $employee->forceFill(['user_id' => $user->id])->save();   // user_id is not fillable on purpose

        return $employee;
    }
}
