<?php

namespace Tests\Feature\Components;

use Tests\TestCase;

class CardTest extends TestCase
{
    public function test_card_renders_heading_content_actions_and_footer(): void
    {
        $view = $this->blade(
            '<x-ui.card heading="Employees" subheading="All staff">
                <x-slot:actions><button>Add</button></x-slot:actions>
                Card body
                <x-slot:footer>Showing 10</x-slot:footer>
            </x-ui.card>'
        );

        $view->assertSeeInOrder(['Employees', 'All staff', 'Add', 'Card body', 'Showing 10']);
        $view->assertSee('glass', false);
    }

    public function test_card_without_heading_has_no_header_bar(): void
    {
        $view = $this->blade('<x-ui.card>Only content</x-ui.card>');

        $view->assertSee('Only content');
        $view->assertDontSee('border-b', false);
    }

    public function test_extra_classes_are_merged_and_padding_can_be_removed(): void
    {
        $view = $this->blade('<x-ui.card class="mt-4" :padding="false">Table</x-ui.card>');

        $view->assertSee('glass overflow-hidden rounded-2xl mt-4', false);
        $view->assertDontSee('p-6', false);
    }
}
