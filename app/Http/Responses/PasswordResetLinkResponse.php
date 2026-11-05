<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * "Forgot password" answer. Always the same message, whether the email exists or not,
 * so the form cannot be used to find out who has an account.
 * (Fortify's default says "We can't find a user with that email address.")
 */
class PasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public function __construct(protected ?string $status = null) {}

    /** @param  Request  $request */
    public function toResponse($request): RedirectResponse
    {
        return back()->with('status', __('A reset link will be sent if the account exists.'));
    }
}
