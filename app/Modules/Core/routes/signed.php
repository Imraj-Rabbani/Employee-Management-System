<?php

use App\Modules\Core\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

// Prefix "/invitation", name "invitation." and the "signed" middleware come from CoreServiceProvider.
// The POST uses the same signed URL (the form posts back to the address it was opened from).
Route::get('{user}', [InvitationController::class, 'show'])->name('show')->whereNumber('user');
Route::post('{user}', [InvitationController::class, 'store'])->name('store')->whereNumber('user');
