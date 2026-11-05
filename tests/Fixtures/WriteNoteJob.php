<?php

namespace Tests\Fixtures;

use App\Core\Tenancy\TenantAwareJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Test-only queued job: writes one note into "the current company's" database.
 * Used to prove that TenantAware runs the job against the right tenant DB.
 */
class WriteNoteJob implements ShouldQueue
{
    use Queueable, TenantAwareJob;

    public function __construct(public string $text)
    {
        $this->rememberTenant();
    }

    public function handle(): void
    {
        Note::create(['text' => $this->text]);
    }
}
