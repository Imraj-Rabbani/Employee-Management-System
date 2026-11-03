<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use App\Modules\Core\Livewire\UiKit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class UiKitPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_ui_kit_page_renders(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/core/ui-kit')
            ->assertOk()
            ->assertSeeLivewire(UiKit::class)
            ->assertSee('Add employee');
    }

    public function test_saving_an_empty_form_shows_validation_errors(): void
    {
        Livewire::test(UiKit::class)
            ->call('save')
            ->assertHasErrors(['name' => 'required', 'email' => 'required', 'department' => 'required', 'joined_on' => 'required'])
            ->assertSee('The name field is required.')
            ->assertNotDispatched('toast');
    }

    public function test_saving_a_valid_form_shows_a_toast_and_clears_it(): void
    {
        Livewire::test(UiKit::class)
            ->set('name', 'Rahim Uddin')
            ->set('email', 'rahim@example.com')
            ->set('department', 'hr')
            ->set('joined_on', now()->subYear()->toDateString())
            ->set('photo', UploadedFile::fake()->image('rahim.jpg'))
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast', message: 'Rahim Uddin saved', variant: 'success')
            ->assertSet('name', '');
    }

    public function test_future_joining_date_is_rejected(): void
    {
        Livewire::test(UiKit::class)
            ->set('joined_on', now()->addDay()->toDateString())
            ->call('save')
            ->assertHasErrors(['joined_on' => 'before_or_equal']);
    }

    public function test_required_labels_show_an_asterisk_not_escaped_html(): void
    {
        // Regression: the asterisk <span> was once escaped and shown as text on this page.
        Livewire::test(UiKit::class)
            ->assertSeeHtml('aria-hidden="true">*</span>')
            ->assertDontSeeHtml('&lt;span');
    }

    public function test_validation_error_appears_under_the_field(): void
    {
        Livewire::test(UiKit::class)
            ->call('save')
            ->assertSeeInOrder(['Full name', 'The name field is required.', 'Email']);
    }
}
