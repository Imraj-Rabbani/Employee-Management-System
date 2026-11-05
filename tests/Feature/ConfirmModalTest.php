<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class ConfirmModalTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    public function test_layout_contains_the_confirm_modal(): void
    {
        $this->actingAsTenantUser();

        $this->get('/dashboard')
            ->assertSee('x-on:confirm.window', false)
            ->assertSee('data-modal="confirm"', false);
    }

    public function test_confirmed_action_runs_on_the_component(): void
    {
        Livewire::test('hrm::ping')
            ->call('increment')
            ->call('resetCount')
            ->assertSet('count', 0)
            ->assertDispatched('toast', message: 'Counter reset', variant: 'info');
    }
}
