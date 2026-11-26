<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Blade does NOT run directives such as @js(...) inside a component tag's attributes (<flux:button x-on:click="...">,
 * <x-ui.modal ...>): the text "@js(...)" reaches the browser as it is, the Alpine expression breaks and the
 * button does nothing (found 2026-11-26: "Send scores" and 168 other confirm buttons).
 * Inside a component tag write {{ Js::from(...) }} instead. In plain HTML tags (<div x-data="...">) @js works.
 */
class BladeComponentAttributesTest extends TestCase
{
    public function test_no_component_tag_uses_the_js_directive_in_its_attributes(): void
    {
        $offenders = [];
        $files = [...File::allFiles(app_path()), ...File::allFiles(resource_path('views'))];

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            // A component tag up to its closing ">" (a ">" inside "..." such as "=>" does not end it).
            preg_match_all('/<(?:flux:|x-)[\w.:-]+(?:[^>"]|"[^"]*")*>/s', $file->getContents(), $tags);
            foreach ($tags[0] as $tag) {
                if (str_contains($tag, '@js(')) {
                    $offenders[] = $file->getRelativePathname().': '.strtok($tag, "\n");
                }
            }
        }

        $this->assertSame([], $offenders, 'Use {{ Js::from(...) }} instead of @js(...) inside component tags.');
    }
}
