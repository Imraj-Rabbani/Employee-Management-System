<?php

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Public landing page. Signed-in users are taken straight to their start page.
Route::get('/', function () {
    if (! auth()->check()) {
        return view('landing');
    }

    /** @var User $user */
    $user = auth()->user();

    return redirect()->route($user->isSuperAdmin() ? 'admin.dashboard' : 'dashboard');
})->name('home');

Route::view('/pricing', 'pricing')->name('pricing');

// The company dashboard runs inside the user's company ("tenant"), like module pages.
Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified', 'tenant', 'tenant.settings'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    // Demo for Proves PDF generation works. Remove once real payslips exist.
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
