<?php

use App\Modules\Workflow\Http\Controllers\EmailApprovalController;
use Illuminate\Support\Facades\Route;

// Prefix "/workflow/email", name "workflow." and the "signed" middleware come from WorkflowServiceProvider.
// The POST uses the same signed URL (the form posts back to the address it was opened from).
Route::get('{tenant}/{approval}', [EmailApprovalController::class, 'show'])->name('respond')->whereNumber('approval');
Route::post('{tenant}/{approval}', [EmailApprovalController::class, 'store'])->name('respond.store')->whereNumber('approval');
