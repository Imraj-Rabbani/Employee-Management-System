<?php

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// "/" = "take me to my start page". Used after login and by the logo / Dashboard link.
// Guests are sent to login (auth middleware); super admins go to Platform admin,
// everyone else to their company's dashboard.
Route::get('/', function () {
    /** @var User $user */
    $user = auth()->user();

    return redirect()->route($user->isSuperAdmin() ? 'admin.dashboard' : 'dashboard');
})->middleware('auth')->name('home');

// The company dashboard runs inside the user's company ("tenant"), like module pages.
Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified', 'tenant'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    // Demo for task 1.2e: proves PDF generation works. Remove once real payslips exist.
    Route::get('hello-pdf', function () {
        return Pdf::loadView('pdf.hello', [
            'name' => auth()->user()->name,
            'generatedAt' => now()->format('d M Y, H:i'),
        ])->stream('hello.pdf');
    })->name('hello-pdf');

    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    // Asks for the password first (Fortify's confirm-password page), then again after 3 hours.
    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware('password.confirm')
        ->name('settings.two-factor');
});

// Super-admin (platform) area: manage companies. No "tenant" middleware: it works on the central database only.
Route::middleware(['auth', 'can:super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'admin.dashboard')->name('dashboard');
});

require __DIR__.'/auth.php';
