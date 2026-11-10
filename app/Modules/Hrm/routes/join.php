<?php

use App\Modules\Hrm\Http\Controllers\JoinController;
use Illuminate\Support\Facades\Route;

// Prefix "/join", name "join." and the "signed" middleware come from HrmServiceProvider.
// The POST uses the same signed URL (the form posts back to the address it was opened from).
Route::get('{user}', [JoinController::class, 'show'])->name('show')->whereNumber('user');
Route::post('{user}', [JoinController::class, 'store'])->name('store')->whereNumber('user');
