<?php

namespace Tests\Feature\Components;

use Tests\TestCase;

class UiKitComponentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // In a real request the "web" middleware shares $errors with every view.
        // $this->blade() renders without a request, so we share an empty one ourselves.
        $this->withViewErrors([]);
    }

    public function test_badge_uses_the_requested_color_and_dot(): void
    {
        $view = $this->blade('<x-ui.badge color="success" dot>Approved</x-ui.badge>');

        $view->assertSee('Approved');
        $view->assertSee('text-green-700', false);
        $view->assertSee('rounded-full bg-current', false);
    }

    public function test_unknown_badge_color_falls_back_to_neutral(): void
    {
        $this->blade('<x-ui.badge color="purple">X</x-ui.badge>')->assertSee('text-zinc-700', false);
    }

    public function test_stat_shows_label_value_and_trend_direction(): void
    {
        $view = $this->blade('<x-ui.stat label="Employees" value="240" trend="+4" :trend-up="true" />');

        $view->assertSeeInOrder(['Employees', '240', '+4']);
        $view->assertSee('text-green-600', false);
    }

    public function test_empty_state_shows_heading_description_and_actions(): void
    {
        $view = $this->blade('<x-ui.empty heading="No employees" description="Add one"><button>Add</button></x-ui.empty>');

        $view->assertSeeInOrder(['No employees', 'Add one', 'Add']);
    }

    public function test_required_input_shows_a_red_asterisk(): void
    {
        $view = $this->blade('<x-form.input name="email" label="Email" hint="Used for login" required />');

        $view->assertSeeInOrder(['Email', 'aria-hidden="true">*</span>', 'Used for login'], false);
        $view->assertSee('aria-required="true"', false);
        $view->assertSee('name="email"', false);
        $view->assertDontSee('Required');
    }

    public function test_optional_input_has_no_asterisk(): void
    {
        $view = $this->blade('<x-form.input name="notes" label="Notes" />');

        $view->assertDontSee('aria-hidden="true">*</span>', false);
        $view->assertDontSee('aria-required', false);
    }

    public function test_label_text_is_still_escaped(): void
    {
        $view = $this->blade('<x-form.input name="x" label="<b>Bold</b>" required />');

        $view->assertSee('&lt;b&gt;Bold&lt;/b&gt;', false);
        $view->assertDontSee('<b>Bold</b>', false);
    }

    public function test_input_shows_the_validation_error_for_its_field(): void
    {
        $view = $this->withViewErrors(['email' => 'The email field is required.'])
            ->blade('<x-form.input wire:model="email" label="Email" />');

        $view->assertSee('The email field is required.');
    }

    public function test_select_renders_placeholder_and_options(): void
    {
        $view = $this->blade(
            '<x-form.select name="dept" label="Department" :options="$options" placeholder="Choose" />',
            ['options' => ['hr' => 'Human Resources', 'it' => 'IT']],
        );

        $view->assertSeeInOrder(['Choose', 'Human Resources', 'IT']);
        $view->assertSee('value="hr"', false);
    }

    public function test_date_textarea_file_and_toggle_render(): void
    {
        $this->blade('<x-form.date name="d" label="Joining date" />')->assertSee('type="date"', false);
        $this->blade('<x-form.textarea name="n" label="Notes" />')->assertSee('<textarea', false);
        $this->blade('<x-form.file wire:model="photo" label="Photo" />')->assertSee('type="file"', false)->assertSee('Uploading');
        $this->blade('<x-form.toggle name="active" label="Active" />')->assertSee('Active')->assertSee('data-flux-switch', false);
    }

    public function test_modal_renders_heading_body_and_footer_with_cancel(): void
    {
        $view = $this->blade(
            '<x-ui.modal name="employee-form" heading="Add employee">Body<x-slot:footer><button>Save</button></x-slot:footer></x-ui.modal>'
        );

        $view->assertSeeInOrder(['Add employee', 'Body', 'Cancel', 'Save']);
        $view->assertSee('data-modal="employee-form"', false);
        $view->assertDontSee('glass', false);
    }

    public function test_large_modal_is_wider(): void
    {
        $this->blade('<x-ui.modal name="x" size="lg">Body</x-ui.modal>')->assertSee('md:max-w-3xl', false);
    }
}
