<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
