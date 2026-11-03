<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrmPingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/hrm/ping')->assertRedirect('/login');
    }

    public function test_hrm_module_renders_its_ping_view(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/hrm/ping')
            ->assertOk()
            ->assertViewIs('hrm::ping')
            ->assertSee('Hrm module is alive');
    }
}
