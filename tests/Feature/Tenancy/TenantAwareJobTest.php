<?php

namespace Tests\Feature\Tenancy;

use App\Core\Tenancy\TenantManager;
use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\Concerns\CreatesTenantDatabases;
use Tests\Fixtures\Note;
use Tests\Fixtures\WriteNoteJob;
use Tests\TestCase;

/**
 * Task 2.4 "Done when": a queued job writes to the database of the company
 * that dispatched it, even though the worker itself has no tenant active.
 */
class TenantAwareJobTest extends TestCase
{
    use CreatesTenantDatabases;
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    private TenantManager $tenants;

    protected function setUp(): void
    {
        parent::setUp();

        $notesTable = fn () => Schema::connection('tenant')->create('notes', function (Blueprint $table) {
            $table->id();
            $table->string('text');
        });

        $this->alpha = $this->createMigratedTenant(schema: $notesTable);
        $this->beta = $this->createMigratedTenant(schema: $notesTable);
        $this->tenants = app(TenantManager::class);
    }

    public function test_a_queued_job_runs_against_the_tenant_that_dispatched_it(): void
    {
        // Use the real database queue (phpunit.xml sets "sync" for other tests).
        config(['queue.default' => 'database']);

        // 1. "Web request" for Alpha: dispatch the job, then the request ends.
        $this->tenants->switchTo($this->alpha);
        WriteNoteJob::dispatch('Written by the queue');
        $this->tenants->forget();

        // The job is waiting in the central "jobs" table, and nothing has been written yet.
        $this->assertSame(1, DB::connection('central')->table('jobs')->count());

        // 2. Worker: no tenant active. Run exactly one job, like `php artisan queue:work --once`.
        $this->assertNull(tenant());
        Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

        // 3. The note is in Alpha's database, not Beta's.
        $this->assertSame(0, DB::connection('central')->table('jobs')->count());
        $this->assertSame(['Written by the queue'], $this->notesOf($this->alpha));
        $this->assertSame([], $this->notesOf($this->beta));

        // And the worker is back to "no tenant" for the next job.
        $this->assertNull(tenant());
    }

    public function test_a_sync_job_gives_the_request_its_own_tenant_back(): void
    {
        // Beta's request dispatches a job that was created for Alpha.
        $this->tenants->switchTo($this->alpha);
        $job = new WriteNoteJob('For Alpha');

        $this->tenants->switchTo($this->beta);
        dispatch_sync($job);

        $this->assertSame($this->beta->id, tenant()?->id);
        $this->assertSame(['For Alpha'], $this->notesOf($this->alpha));
    }

    public function test_a_job_created_without_a_tenant_fails_loudly(): void
    {
        $this->assertNull(tenant());

        $this->expectException(LogicException::class);

        dispatch_sync(new WriteNoteJob('No company'));
    }

    /** @return list<string> */
    private function notesOf(Tenant $tenant): array
    {
        $this->tenants->switchTo($tenant);
        $texts = Note::orderBy('id')->pluck('text')->all();
        $this->tenants->forget();

        return $texts;
    }
}
