<?php

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
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
});

// Super-admin (platform) area: manage companies. No "tenant" middleware: it works on the central database only.
Route::middleware(['auth', 'can:super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'admin.dashboard')->name('dashboard');
});

require __DIR__.'/auth.php';
