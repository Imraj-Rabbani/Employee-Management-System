<?php

namespace Tests\Feature\Modules;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/** Every module's routes are loaded (prefix + name) and need a login. One real page per module. */
class ModuleRoutesTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function pages(): array
    {
        return [
            'core' => ['/core/settings'],
            'workflow' => ['/workflow/inbox'],
            'hrm' => ['/hrm/employees'],
            'payroll' => ['/payroll/runs'],
        ];
    }

    #[DataProvider('pages')]
    public function test_guests_are_redirected_to_the_login_page(string $url): void
    {
        $this->get($url)->assertRedirect('/login');
    }

    #[DataProvider('pages')]
    public function test_the_page_opens_for_a_company_administrator(string $url): void
    {
        $this->actingAsTenantUser();

        $this->get($url)->assertOk();
    }
}
