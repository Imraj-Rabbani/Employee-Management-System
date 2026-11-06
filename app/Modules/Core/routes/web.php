<?php

use Illuminate\Support\Facades\Route;

// Prefix "/core" and name "core." are added by ModuleServiceProvider.
// can:... = the permission needed (permissions.php). Livewire re-checks it on every click (persistent middleware).
Route::view('ping', 'core::ping')->name('ping');
Route::view('ui-kit', 'core::ui-kit')->name('ui-kit');
Route::view('users', 'core::users')->middleware('can:core.users.view')->name('users');
Route::view('settings', 'core::company-settings')->middleware('can:core.settings.manage')->name('settings');
Route::view('fiscal-years', 'core::fiscal-years')->middleware('can:core.fiscal_years.manage')->name('fiscal-years');
Route::view('roles', 'core::roles')->middleware('can:core.roles.manage')->name('roles');
Route::view('currencies', 'core::currencies')->middleware('can:core.settings.manage')->name('currencies');
