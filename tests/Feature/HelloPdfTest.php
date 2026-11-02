<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelloPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get('/hello-pdf');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_get_a_pdf(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/hello-pdf');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
