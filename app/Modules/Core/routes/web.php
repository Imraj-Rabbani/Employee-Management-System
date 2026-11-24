<?php

use Illuminate\Support\Facades\Route;

// Prefix "/core" and name "core." are added by ModuleServiceProvider.
// can:... = the permission needed (permissions.php). Livewire re-checks it on every click (persistent middleware).
Route::view('users', 'core::users')->middleware('can:core.users.view')->name('users');
Route::view('settings', 'core::company-settings')->middleware('can:core.settings.manage')->name('settings');
Route::view('roles', 'core::roles')->middleware('can:core.roles.manage')->name('roles');

// Global Config (sidebar, bottom): opens the first tab the user may see (config/sections.php).
Route::get('config', function () {
    foreach (config('sections.core.config.tabs') as $tab) {
        if (auth()->user()->can($tab['can'])) {
            return redirect()->route($tab['route']);
        }
    }
    abort(403);
})->name('config');
