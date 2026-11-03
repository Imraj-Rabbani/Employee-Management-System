<?php

namespace App\Modules\Core\Livewire;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Showcase of the UI kit (task 1.6). Not a real feature: nothing is saved.
 */
class UiKit extends Component
{
    use WithFileUploads;

    #[Validate('required|string|max:100')]
    public string $name = '';

    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|in:hr,finance,it')]
    public string $department = '';

    #[Validate('required|date|before_or_equal:today')]
    public string $joined_on = '';

    #[Validate('nullable|string|max:500')]
    public string $notes = '';

    #[Validate('nullable|image|max:2048')]
    public ?UploadedFile $photo = null;

    public bool $active = true;

    /** @return array<string, string> */
    public function departments(): array
    {
        return ['hr' => __('Human Resources'), 'finance' => __('Finance'), 'it' => __('IT')];
    }

    public function save(): void
    {
        $this->validate();

        Flux::modal('employee-form')->close();
        $this->dispatch('toast', message: __(':name saved', ['name' => $this->name]), variant: 'success');
        $this->reset();
    }

    public function render(): View
    {
        return view('core::livewire.ui-kit');
    }
}
