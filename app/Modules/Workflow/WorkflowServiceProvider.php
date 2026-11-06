<?php

namespace App\Modules\Workflow;

use App\Core\Support\ModuleServiceProvider;
use App\Modules\Workflow\Events\ApprovalAnswered;
use App\Modules\Workflow\Events\ApprovalCompleted;
use App\Modules\Workflow\Events\ApprovalRequested;
use App\Modules\Workflow\Listeners\NotifyApprover;
use App\Modules\Workflow\Listeners\NotifyRequester;
use App\Modules\Workflow\Listeners\RunDocumentHandler;
use App\Modules\Workflow\View\Components\ApprovalTimeline;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

class WorkflowServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'workflow';

    public function boot(): void
    {
        parent::boot();

        // Laravel only finds listeners in app/Listeners by itself; module listeners are registered here.
        Event::listen(ApprovalCompleted::class, RunDocumentHandler::class);
        Event::listen(ApprovalRequested::class, NotifyApprover::class);
        Event::listen(ApprovalAnswered::class, NotifyRequester::class);

        // <x-approval-timeline :document="$leave" /> on any page, from any module.
        Blade::component('approval-timeline', ApprovalTimeline::class);

        // Approve / reject from the email: NO login, so these routes are outside the module's
        // "auth" + "tenant" group. "signed" rejects any URL whose signature is wrong or expired.
        Route::middleware(['web', 'signed', 'throttle:30,1'])
            ->prefix('workflow/email')
            ->name('workflow.')
            ->group($this->modulePath().'/routes/signed.php');
    }
}
