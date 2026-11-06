<?php

namespace Tests\Feature\Core;

use App\Core\Tenancy\TenantManager;
use App\Models\User;
use App\Modules\Core\Livewire\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 2.11: languages (per user), and the company's timezone and date format. [FND-08]
 */
class LocalisationTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private string $appTimezone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appTimezone = config()->string('app.timezone');

        // A test-only company page that tells which timezone is in use.
        Route::middleware(['web', 'auth', 'tenant', 'tenant.settings'])->get('/_test/timezone', fn () => config('app.timezone'));
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->appTimezone);

        parent::tearDown();
    }

    // ---------------------------------------------------------------- date format

    public function test_dates_use_the_default_format_without_a_company(): void
    {
        $this->assertSame('05 Nov 2026', format_date('2026-11-05'));
        $this->assertSame('', format_date(null));
    }

    public function test_dates_use_the_companys_format(): void
    {
        $this->actingAsTenantUser();
        settings()->set('date_format', 'd/m/Y');

        $this->assertSame('05/11/2026', format_date('2026-11-05'));
    }

    public function test_company_settings_saves_a_date_format_from_the_list_only(): void
    {
        $this->actingAsTenantUser();

        Livewire::test(CompanySettings::class)
            ->assertSet('dateFormat', 'd M Y')
            ->set('dateFormat', 'Y-m-d')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('Y-m-d', settings('date_format'));

        Livewire::test(CompanySettings::class)->set('dateFormat', 'l jS F')->call('save')->assertHasErrors('dateFormat');
    }

    // ---------------------------------------------------------------- timezone

    public function test_company_pages_run_in_the_companys_timezone(): void
    {
        $this->actingAsTenantUser();
        settings()->set('timezone', 'Africa/Nairobi');
        app(TenantManager::class)->forget();   // a real request starts with no company

        $this->get('/_test/timezone')->assertOk()->assertSee('Africa/Nairobi');
        $this->assertSame('Africa/Nairobi', date_default_timezone_get());
    }

    public function test_the_default_timezone_is_used_until_the_company_chooses_one(): void
    {
        $this->actingAsTenantUser();
        app(TenantManager::class)->forget();

        $this->get('/_test/timezone')->assertSee(CompanySettings::DEFAULTS['timezone']);
    }

    // ---------------------------------------------------------------- language

    public function test_english_is_the_default(): void
    {
        $this->actingAsTenantUser();

        $this->get('/dashboard')->assertSee('Fiscal Years')->assertDontSee('অর্থবছর');
    }

    public function test_a_user_who_chose_bangla_sees_the_app_in_bangla(): void
    {
        $this->actingAsTenantUser(['locale' => 'bn']);

        $this->get('/dashboard')
            ->assertSee('ড্যাশবোর্ড')
            ->assertSee('অর্থবছর')
            ->assertDontSee('Fiscal Years');
    }

    public function test_the_language_is_chosen_in_the_profile(): void
    {
        $user = $this->actingAsTenantUser();

        Volt::test('settings.profile')
            ->assertSet('locale', 'en')
            ->set('locale', 'bn')
            ->call('updateProfileInformation')
            ->assertHasNoErrors()
            ->assertRedirect(route('settings.profile'));

        $this->assertSame('bn', $user->fresh()?->locale);

        Volt::test('settings.profile')->set('locale', 'fr')->call('updateProfileInformation')->assertHasErrors('locale');
    }

    public function test_the_locale_is_not_mass_assigned_to_something_unknown_by_the_middleware(): void
    {
        $this->actingAs(User::factory()->create(['locale' => 'xx']));

        $this->get('/settings/profile')->assertOk();
        $this->assertSame('en', app()->getLocale());
    }

    public function test_every_sidebar_label_has_a_bangla_translation(): void
    {
        $bangla = json_decode((string) file_get_contents(lang_path('bn.json')), true);
        $missing = [];

        foreach (config()->array('navigation') as $group) {
            foreach ([$group['heading'], ...array_column($group['items'], 'label')] as $text) {
                if (! isset($bangla[$text]) && $text !== 'UI Kit') {
                    $missing[] = $text;
                }
            }
        }

        $this->assertSame([], $missing, 'Add these to lang/bn.json.');
    }
}
