<?php

use Illuminate\Support\Facades\Route;

// Prefix "/workflow" and name "workflow." are added by ModuleServiceProvider.
// can:... = the permission needed (permissions.php). Livewire re-checks it on every click (persistent middleware).
Route::view('ping', 'workflow::ping')->name('ping');
Route::view('inbox', 'workflow::inbox')->name('inbox');   // everyone: only their own approvals
Route::view('setup', 'workflow::setup')->middleware('can:workflow.setup.manage')->name('setup');
