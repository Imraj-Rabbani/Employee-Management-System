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

    public function render(): View
    {
        return view('hrm::livewire.ping');
    }
}
