<?php

namespace App\Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The page behind the invitation email: the invited person sets their password. No login needed.
 *
 * Why this is safe:
 *   - the URL is SIGNED and expires ("signed" middleware): nobody can make one up or change the user id
 *   - it carries v = the user's invitationFingerprint(), which changes when the password changes, so the
 *     link works only ONCE, and a re-sent invitation (new random password) kills the older link
 */
class InvitationController
{
    public function show(Request $request, User $user): View
    {
        return view('core::invitation.accept', [
            'user' => $user,
            'valid' => $this->isOpen($request, $user),
            'actionUrl' => $request->fullUrl(),   // the form posts back to the same signed URL
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        abort_unless($this->isOpen($request, $user), 403, __('This invitation link no longer works.'));

        // No "confirm password" field: the box has a show/hide eye to check what was typed.
        $data = $request->validate(['password' => ['required', 'string', Password::default()]]);

        $user->forceFill([
            'password' => $data['password'],             // hashed by the cast
            'invitation_accepted_at' => now(),
            'email_verified_at' => $user->email_verified_at ?? now(),   // they opened a link sent to it
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('toast', ['message' => __('Welcome! Your password is set.'), 'variant' => 'success']);
    }

    private function isOpen(Request $request, User $user): bool
    {
        return $user->hasOpenInvitation()
            && hash_equals($user->invitationFingerprint(), (string) $request->query('v'));
    }
}
