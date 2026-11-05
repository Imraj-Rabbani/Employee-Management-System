<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 2.7b: "confirm your password" screen (Fortify), used before sensitive actions.
 */
class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->for(Tenant::factory())->create();
    }

    public function test_confirm_password_screen_can_be_rendered(): void
    {
        $this->actingAs($this->user)
            ->get('/user/confirm-password')
            ->assertOk()
            ->assertSee(route('password.confirm.store'));
    }

    public function test_password_can_be_confirmed(): void
    {
        $this->actingAs($this->user)
            ->post('/user/confirm-password', ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/')
            ->assertSessionHas('auth.password_confirmed_at');
    }

    public function test_password_is_not_confirmed_with_invalid_password(): void
    {
        $this->actingAs($this->user)
            ->post('/user/confirm-password', ['password' => 'wrong-password'])
            ->assertSessionHasErrors(['password']);
    }
}
