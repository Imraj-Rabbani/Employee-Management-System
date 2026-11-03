<?php

namespace App\Modules\Hrm\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Ping extends Component
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }

    public function decrement(): void
    {
        $this->count--;
    }

    /** Demo for the toast component (task 1.5f). */
    public function save(): void
    {
        $this->dispatch('toast', message: __('Saved successfully'), variant: 'success');
    }

    /** Demo for the confirm modal (task 1.5g). Only called after the user confirms. */
    public function resetCount(): void
    {
        $this->count = 0;
        $this->dispatch('toast', message: __('Counter reset'), variant: 'info');
    }

    public function render(): View
    {
        return view('hrm::livewire.ping');
    }
}
