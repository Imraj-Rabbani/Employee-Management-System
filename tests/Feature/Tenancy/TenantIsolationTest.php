<?php

namespace Tests\Feature\Tenancy;

use App\Core\Tenancy\TenantManager;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesTenantDatabases;
use Tests\Fixtures\Note;
use Tests\TestCase;

/**
 * Task 2.2 "Done when": two tenants each see only their own data.
 * Uses real databases for both tenants and a real HTTP request through IdentifyTenant.
 */
class TenantIsolationTest extends TestCase
{
    use CreatesTenantDatabases;
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $notesTable = fn () => Schema::connection('tenant')->create('notes', function (Blueprint $table) {
            $table->id();
            $table->string('text');
        });

        $this->alpha = $this->createMigratedTenant(schema: $notesTable);
        $this->beta = $this->createMigratedTenant(schema: $notesTable);

        $this->addNotes($this->alpha, ['Alpha note 1', 'Alpha note 2']);
        $this->addNotes($this->beta, ['Beta note 1']);

        // A test-only page that lists the notes of "the current company".
        Route::middleware(['web', 'auth', 'tenant'])->get('/_test/notes', fn () => Note::orderBy('id')->pluck('text'));
    }

    public function test_each_company_sees_only_its_own_notes_over_http(): void
    {
        $this->actingAs(User::factory()->for($this->alpha)->create())
            ->get('/_test/notes')
            ->assertOk()
            ->assertExactJson(['Alpha note 1', 'Alpha note 2']);

        $this->actingAs(User::factory()->for($this->beta)->create())
            ->get('/_test/notes')
            ->assertOk()
            ->assertExactJson(['Beta note 1']);
    }

    public function test_switching_tenant_switches_what_the_model_reads(): void
    {
        $tenants = app(TenantManager::class);

        $tenants->switchTo($this->alpha);
        $this->assertSame(2, Note::count());

        $tenants->switchTo($this->beta);
        $this->assertSame(1, Note::count());
        $this->assertSame('Beta note 1', Note::first()?->text);
    }

    public function test_tenant_data_is_not_in_the_central_database(): void
    {
        $this->assertFalse(Schema::connection('central')->hasTable('notes'));
    }

    /** @param  list<string>  $texts */
    private function addNotes(Tenant $tenant, array $texts): void
    {
        app(TenantManager::class)->switchTo($tenant);

        foreach ($texts as $text) {
            Note::create(['text' => $text]);
        }

        app(TenantManager::class)->forget();
    }
}
