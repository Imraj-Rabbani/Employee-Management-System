<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
| Login, logout, forgot/reset password and password confirmation are Fortify routes
| (see FortifyServiceProvider and config/fortify.php). Company sign-up and e-mail verification are ours.
*/

// Public company sign-up (RegisterCompany). The company starts inactive until a super admin approves it.
Route::view('register', 'auth.register')->middleware('guest')->name('register');

Route::middleware('auth')->group(function () {
    Volt::route('verify-email', 'auth.verify-email')
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
});
