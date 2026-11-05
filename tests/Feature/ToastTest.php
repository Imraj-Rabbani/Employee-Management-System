<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class ToastTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    public function test_layout_contains_the_toast_container(): void
    {
        $this->actingAsTenantUser();

        $this->get('/dashboard')->assertSee('x-on:toast.window', false);
    }

    public function test_livewire_component_can_dispatch_a_toast(): void
    {
        Livewire::test('hrm::ping')
            ->call('save')
            ->assertDispatched('toast', message: 'Saved successfully', variant: 'success');
    }

    public function test_flashed_toast_is_shown_after_a_redirect(): void
    {
        $this->actingAsTenantUser();

        $this->withSession(['toast' => ['message' => 'Employee saved', 'variant' => 'success']])
            ->get('/dashboard')
            ->assertSee('Employee saved');
    }
}
