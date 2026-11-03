<?php

use Illuminate\Support\Facades\Route;

// Prefix "/workflow" and name "workflow." are added by ModuleServiceProvider.
Route::view('ping', 'workflow::ping')->name('ping');
