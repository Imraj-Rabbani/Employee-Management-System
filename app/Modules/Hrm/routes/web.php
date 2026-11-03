<?php

use Illuminate\Support\Facades\Route;

// Prefix "/hrm" and name "hrm." are added by HrmServiceProvider.
Route::view('ping', 'hrm::ping')->name('ping');
