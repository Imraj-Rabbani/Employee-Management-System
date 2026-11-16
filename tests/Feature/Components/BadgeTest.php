<?php

namespace Tests\Feature\Components;

use Tests\TestCase;

class BadgeTest extends TestCase
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
}
