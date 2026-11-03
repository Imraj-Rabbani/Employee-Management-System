<?php

use Illuminate\Support\Facades\Route;

// Prefix "/core" and name "core." are added by ModuleServiceProvider.
Route::view('ping', 'core::ping')->name('ping');
