<?php

use Illuminate\Support\Facades\Route;

// Prefix "/core" and name "core." are added by ModuleServiceProvider.
Route::view('ping', 'core::ping')->name('ping');
Route::view('ui-kit', 'core::ui-kit')->name('ui-kit');
Route::view('users', 'core::users')->name('users');
