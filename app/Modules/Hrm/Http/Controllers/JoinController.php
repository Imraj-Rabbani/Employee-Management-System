<?php

namespace App\Modules\Hrm\Http\Controllers;

use App\Actions\Fortify\PasswordValidationRules;
use App\Core\Tenancy\TenantManager;
use App\Models\User;
use App\Modules\Hrm\Actions\CreateEmployeeForUser;
use App\Modules\Hrm\Actions\UserInvitations;
use App\Modules\Hrm\Enums\Gender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The page behind an Employees → Invite user email: the invited person fills in their details
 * (name, Employee ID, ...) and a password. That creates their employee record, and logs them in.
 *
 * Safe for the same reasons as Core's InvitationController (5.7):
 *   - the URL is SIGNED and expires ("signed" middleware): nobody can make one up or change the user id
 *   - it carries v = the user's invitationFingerprint(), which changes when the password changes,
 *     so the link works only ONCE, and a re-sent invitation kills the older link
 * Also refused: a cancelled invitation (login deleted → 404), and an inactive or expired company.
 */
class JoinController
{
    use PasswordValidationRules;

    public function __construct(
        private UserInvitations $invitations,
        private TenantManager $tenants,
    ) {}

    public function show(Request $request, User $user): View
    {
        return view('hrm::join.show', [
            'user' => $user,
            'company' => $user->tenant,
            'valid' => $this->isOpen($request, $user),
            'actionUrl' => $request->fullUrl(),   // the form posts back to the same signed URL
            'genders' => Gender::options(),
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        abort_unless($this->isOpen($request, $user), 403, __('This invitation link no longer works.'));

        // isOpen() switched to the user's company, so "Employee ID already taken" is checked there.
        $data = $request->validate([
            ...CreateEmployeeForUser::rules(),
            'password' => $this->passwordRules(),
        ], attributes: ['code' => __('employee ID'), 'first_name' => __('first name')]);

        $this->invitations->accept($user, $data, $data['password']);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('toast', ['message' => __('Welcome to :company!', ['company' => $user->tenant?->name]), 'variant' => 'success']);
    }

    /** Signature fine (middleware), fingerprint matches, company usable, and still a pending invitation there. */
    private function isOpen(Request $request, User $user): bool
    {
        $company = $user->tenant;

        if (! $user->hasOpenInvitation()
            || ! hash_equals($user->invitationFingerprint(), (string) $request->query('v'))
            || $company === null || $company->accessProblem() !== null || ! $company->hasModule('hrm')) {
            return false;
        }

        $this->tenants->switchTo($company);

        return $this->invitations->findPending($user->id) !== null;
    }
}
