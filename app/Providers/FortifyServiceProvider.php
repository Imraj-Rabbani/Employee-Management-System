<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\PasswordResetLinkResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

/**
 * Fortify = Laravel's login backend. It owns the auth ROUTES and their logic
 * (POST /login, /logout, /forgot-password, /reset-password, /user/confirm-password, and 2FA later).
 * We only give it our pages (the *View() calls) and our rules (authenticateUsing).
 * Which features are on: config/fortify.php.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Same answer whether or not the email exists (see the class).
        $this->app->singleton(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->singleton(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
    }

    public function boot(): void
    {
        // Our pages for Fortify's GET routes (resources/views/auth).
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));

        Fortify::authenticateUsing($this->authenticate(...));
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        // At most 5 login attempts per minute for one email from one IP address.
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->string(Fortify::username())->value()).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }

    /**
     * Who may log in: right email + password, AND a super admin or a user whose company may work.
     * Returning null = "These credentials do not match our records."
     */
    private function authenticate(Request $request): ?User
    {
        $user = User::where('email', $request->string('email')->value())->first();

        if ($user === null || ! Hash::check($request->string('password')->value(), $user->password)) {
            return null;
        }

        if ($user->isSuperAdmin()) {
            return $user;
        }

        // Only after the password was right, so strangers cannot probe which companies exist.
        $problem = $user->tenant === null
            ? __('Your account is not linked to a company.')
            : $user->tenant->accessProblem();

        if ($problem !== null) {
            throw ValidationException::withMessages([Fortify::username() => $problem]);
        }

        return $user;
    }
}
