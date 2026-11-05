<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConfirmModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_contains_the_confirm_modal(): void
    {
        $this->actingAs(User::factory()->for(Tenant::factory())->create());

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
