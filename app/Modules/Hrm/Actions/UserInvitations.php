<?php

namespace App\Modules\Hrm\Actions;

use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\UserRole;
use App\Modules\Core\Notifications\UserInvited;
use App\Modules\Core\Services\Permissions;
use App\Modules\Hrm\Models\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use LogicException;

/**
 * HRM → Employees → Invite user: invite someone who is NOT an employee yet. They register themselves.
 *
 *     $invitations = app(UserInvitations::class);
 *     $invitations->invite('karim@acme.com', $role, auth()->user());   login (no employee yet) + email
 *     $invitations->pending();                                         invited, not registered yet
 *     $invitations->resend($user, auth()->user());                     new link, old one dies
 *     $invitations->cancel($user);                                     the login is deleted
 *     $invitations->accept($user, $details, 'secret123');              (the /join page) employee + password
 *
 * Use inside the company (tenant). The login is made at once (with the chosen role, so the inviter
 * decides what they may see), but with a random password nobody knows: it is unusable until the
 * person opens the emailed link (/join/{user}, JoinController) and fills in their details.
 *
 * Unlike EmployeeLogin::invite() (5.7: HR made the employee already, the person only sets a password),
 * here the employee record is created by the person themselves when they accept.
 */
class UserInvitations
{
    public function __construct(private CreateEmployeeForUser $createEmployee) {}

    public function inviteProblem(string $email): ?string
    {
        return User::where('email', $email)->exists()
            ? __('This email address already has a login.')
            : null;
    }

    public function invite(string $email, ?Role $role, User $invitedBy): User
    {
        if ($problem = $this->inviteProblem($email)) {
            throw new LogicException($problem);
        }

        // Until they register, the email stands in for the name (shown in the pending list).
        $user = new User(['name' => $email, 'email' => $email, 'password' => Str::random(40)]);
        $user->forceFill(['tenant_id' => tenant()?->id])->save();   // tenant_id is not fillable on purpose
        $role?->assignTo($user);

        $this->send($user, $invitedBy);

        return $user;
    }

    /**
     * Logins of this company that were invited here and have not registered yet (no employee record).
     *
     * @return Collection<int, User>
     */
    public function pending(): Collection
    {
        $linked = Employee::withoutGlobalScopes()->whereNotNull('user_id')->pluck('user_id')->all();

        return User::where('tenant_id', tenant()?->id)
            ->whereNotNull('invited_at')->whereNull('invitation_accepted_at')
            ->whereNotIn('id', $linked)
            ->orderByDesc('invited_at')
            ->get();
    }

    /** A pending invitation of THIS company, or null (any other id the browser might send). */
    public function findPending(int $userId): ?User
    {
        return $this->pending()->firstWhere('id', $userId);
    }

    public function resend(User $user, User $invitedBy): void
    {
        $this->send($this->ensurePending($user), $invitedBy);
    }

    /** Deletes the login (and its role): the link in the email stops working. */
    public function cancel(User $user): void
    {
        $this->ensurePending($user);

        UserRole::where('user_id', $user->id)->delete();
        app(Permissions::class)->flush();
        $user->delete();
    }

    /**
     * The person registers: their employee record is created and linked, their name and password saved.
     * Runs inside the user's company (JoinController switches to it).
     *
     * @param  array<string, mixed>  $details  see CreateEmployeeForUser::rules()
     */
    public function accept(User $user, array $details, string $password): Employee
    {
        $employee = $this->createEmployee->handle($this->ensurePending($user), $details);

        $user->forceFill([
            'name' => $employee->name,
            'password' => $password,                   // hashed by the cast; also kills the link
            'invitation_accepted_at' => now(),
            'email_verified_at' => $user->email_verified_at ?? now(),   // they opened a link sent to it
        ])->save();

        return $employee;
    }

    private function ensurePending(User $user): User
    {
        if ($this->findPending($user->id) === null) {
            throw new LogicException('This is not a pending invitation of the current company.');
        }

        return $user;
    }

    /** A new random password (so any older link stops working), then the email with a fresh signed link. */
    private function send(User $user, User $invitedBy): void
    {
        $user->forceFill(['password' => Str::random(40), 'invited_at' => now(), 'invitation_accepted_at' => null])->save();

        $url = URL::temporarySignedRoute(
            'join.show',
            now()->addDays(UserInvited::DAYS_VALID),
            ['user' => $user->id, 'v' => $user->invitationFingerprint()],
        );

        $user->notify(new UserInvited((string) tenant()?->name, $invitedBy->name, $url, register: true));
    }
}
