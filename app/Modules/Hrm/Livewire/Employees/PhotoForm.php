<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Core\Support\TenantUploads;
use App\Modules\Hrm\Models\Employee;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Profile header → "Photo": upload, replace or remove the employee's photo (modal "employee-photo-form"). [EMP-01]
 *
 * WithFileUploads: the file goes to a temporary folder as soon as it is chosen (so we can preview it);
 * save() moves it to the company's own folder via TenantUploads (2.5). The old photo file is deleted.
 */
class PhotoForm extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $employeeId;

    /** The profile tab the page was on, so we can go back to it after saving. */
    #[Locked]
    public ?string $tab = null;

    /** The chosen file, or null. */
    public ?TemporaryUploadedFile $photo = null;

    public function mount(Employee $employee): void
    {
        $this->employeeId = $employee->id;
        // Read now, on the first page load: later clicks go to /livewire/update, which has no ?tab=.
        $this->tab = request()->query('tab') !== null ? (string) request()->query('tab') : null;
    }

    #[On('edit-employee-photo')]
    public function open(): void
    {
        $this->employee();
        $this->reset('photo');
        $this->resetValidation();

        Flux::modal('employee-photo-form')->show();
    }

    public function save(TenantUploads $uploads): void
    {
        $employee = $this->employee();
        $this->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']], attributes: ['photo' => __('photo')]);

        /** @var TemporaryUploadedFile $file */
        $file = $this->photo;
        $old = $employee->photo_path;
        $employee->update(['photo_path' => $uploads->store($file, 'employees/photos')]);
        $uploads->delete($old);

        $this->done($employee, __('Photo saved.'));
    }

    public function remove(TenantUploads $uploads): void
    {
        $employee = $this->employee();
        $uploads->delete($employee->photo_path);
        $employee->update(['photo_path' => null]);

        $this->done($employee, __('Photo removed.'));
    }

    private function employee(): Employee
    {
        $employee = Employee::findOrFail($this->employeeId);
        Gate::authorize('update', $employee);

        return $employee;
    }

    private function done(Employee $employee, string $message): void
    {
        Flux::modal('employee-photo-form')->close();
        session()->flash('toast', ['message' => $message, 'variant' => 'success']);
        $this->redirect(route('hrm.employees.show', ['employee' => $employee, 'tab' => $this->tab]), navigate: true);
    }

    public function render(): View
    {
        return view('hrm::livewire.employees.photo-form', ['employee' => Employee::find($this->employeeId)]);
    }
}
