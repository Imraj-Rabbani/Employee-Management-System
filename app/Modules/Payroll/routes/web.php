<?php

use Illuminate\Support\Facades\Route;

// Prefix "/payroll" and name "payroll." are added by ModuleServiceProvider.
Route::view('ping', 'payroll::ping')->name('ping');
