<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Models\User;
use App\Modules\Hrm\Models\Application;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * One application's history, as a timeline in a modal: every move, who made it, when, the note and the
 * reason. [REC-04] Read only: history rows are never changed.
 *
 *     $dispatch('show-application-history', { id: 12 })     from the pipeline or the applicant's profile
 *
 * Looked up through Application::visibleTo(): an application to a posting outside your org units gives 404.
 */
class ApplicationHistory extends Component
{
    #[Locked]
    public ?int $applicationId = null;

    #[On('show-application-history')]
    public function show(int $id): void
    {
        $this->applicationId = $this->application($id)->id;

        Flux::modal('application-history')->show();
    }

    private function application(int $id): Application
    {
        return Application::query()->visibleTo(Auth::user())
            ->with(['applicant', 'jobPosting', 'stageChanges.rejectionReason'])
            ->findOrFail($id);
    }

    public function render(): View
    {
        $application = $this->applicationId ? $this->application($this->applicationId) : null;

        // Who made each move: logins live in the CENTRAL database, so load them in one query, not one per row.
        $names = $application
            ? User::whereIn('id', $application->stageChanges->pluck('changed_by_user_id')->filter()->unique())->pluck('name', 'id')
            : collect();

        return view('hrm::livewire.recruitment.application-history', compact('application', 'names'));
    }
}
