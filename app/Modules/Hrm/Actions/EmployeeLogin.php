<?php

namespace App\Modules\Hrm\Actions;

use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Notifications\UserInvited;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

/**
 * Gives an employee a login, or takes it away (5.7). [EMP-07]
 *
 *     $logins = app(EmployeeLogin::class);
 *     $logins->invite($employee, 'rahim@acme.com', $role, auth()->user());   new login + email
 *     $logins->resend($employee, auth()->user());                             new link, old one dies
 *     $logins->link($employee, $user);                                        an existing login of this company
 *     $logins->unlink($employee);                                             the login stays, only the link goes
 *
 * Each has a ...Problem() method returning a message for the user (or null), like ChangeOrgUnit.
 *
 * The login lives in the CENTRAL database, the employee in the company one: no transaction can cover
 * both, so invite() removes the new login again if linking fails.
 */
class EmployeeLogin
{
    public function inviteProblem(Employee $employee, string $email): ?string
    {
        return match (true) {
            $employee->user_id !== null => __('This employee already has a login.'),
            User::where('email', $email)->exists() => __('This email address already has a login. Use “Link existing login” if it belongs to this company.'),
            default => null,
        };
    }

    public function invite(Employee $employee, string $email, ?Role $role = null, ?User $invitedBy = null): User
    {
        if ($problem = $this->inviteProblem($employee, $email)) {
            throw new LogicException($problem);
        }

        $user = new User(['name' => $employee->name, 'email' => $email, 'password' => Str::random(40)]);
        $user->forceFill(['tenant_id' => tenant()?->id])->save();   // tenant_id is not fillable on purpose

        try {
            $employee->forceFill(['user_id' => $user->id])->save();   // user_id is not fillable on purpose
            $role?->assignTo($user);
        } catch (Throwable $e) {
            $user->delete();

            throw $e;
        }

        $this->sendInvitation($user, $invitedBy);

        return $user;
    }

    public function resendProblem(Employee $employee): ?string
    {
        $user = $employee->user;

        return match (true) {
            $user === null => __('This employee has no login.'),
            $user->invitation_accepted_at !== null || $user->invited_at === null => __('The login is already in use: ask them to use “Forgot password” instead.'),
            default => null,
        };
    }

    public function resend(Employee $employee, ?User $invitedBy = null): void
    {
        if ($problem = $this->resendProblem($employee)) {
            throw new LogicException($problem);
        }

        /** @var User $user */
        $user = $employee->user;
        $this->sendInvitation($user, $invitedBy);
    }

    public function linkProblem(Employee $employee, User $user): ?string
    {
        $linkedTo = Employee::withoutGlobalScope(VisibleToCurrentUser::class)->where('user_id', $user->id)->first();

        return match (true) {
            $employee->user_id !== null => __('This employee already has a login.'),
            $user->tenant_id === null || $user->tenant_id !== tenant()?->id || $user->isSuperAdmin() => __('This login does not belong to this company.'),
            $linkedTo !== null => __('This login already belongs to :name (:code).', ['name' => $linkedTo->name, 'code' => $linkedTo->code]),
            default => null,
        };
    }

    public function link(Employee $employee, User $user): void
    {
        if ($problem = $this->linkProblem($employee, $user)) {
            throw new LogicException($problem);
        }

        $employee->forceFill(['user_id' => $user->id])->save();
    }

    public function unlink(Employee $employee): void
    {
        $employee->forceFill(['user_id' => null])->save();
    }

    /**
     * A new random password (so any older link stops working), then the email with a fresh signed link.
     */
    private function sendInvitation(User $user, ?User $invitedBy): void
    {
        $user->forceFill(['password' => Str::random(40), 'invited_at' => now(), 'invitation_accepted_at' => null])->save();

        $url = URL::temporarySignedRoute(
            'invitation.show',
            now()->addDays(UserInvited::DAYS_VALID),
            ['user' => $user->id, 'v' => $user->invitationFingerprint()],
        );

        $user->notify(new UserInvited((string) tenant()?->name, $invitedBy?->name, $url));
    }
}
