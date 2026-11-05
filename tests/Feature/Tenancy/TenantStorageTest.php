<?php

namespace Tests\Feature\Tenancy;

use App\Core\Tenancy\TenantManager;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

/**
 * Task 2.5: Storage::disk('tenant') saves into the current company's own folder.
 */
class TenantStorageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    private TenantManager $tenants;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alpha = Tenant::factory()->create(['code' => 'talpha'.strtolower(str()->random(6))]);
        $this->beta = Tenant::factory()->create(['code' => 'tbeta'.strtolower(str()->random(6))]);
        $this->tenants = app(TenantManager::class);
    }

    protected function tearDown(): void
    {
        $this->tenants->forget();
        File::deleteDirectory(storage_path('app/tenants/'.$this->alpha->code));
        File::deleteDirectory(storage_path('app/tenants/'.$this->beta->code));

        parent::tearDown();
    }

    public function test_files_go_into_the_current_tenants_folder(): void
    {
        $this->tenants->switchTo($this->alpha);
        Storage::disk('tenant')->put('hello.txt', 'Alpha');

        $this->assertFileExists(storage_path("app/tenants/{$this->alpha->code}/hello.txt"));
    }

    public function test_each_tenant_sees_only_its_own_files(): void
    {
        $this->tenants->switchTo($this->alpha);
        Storage::disk('tenant')->put('hello.txt', 'Alpha');

        // Same disk name, same file name, but now it is Beta's folder.
        $this->tenants->switchTo($this->beta);
        $this->assertFalse(Storage::disk('tenant')->exists('hello.txt'));

        Storage::disk('tenant')->put('hello.txt', 'Beta');

        $this->tenants->switchTo($this->alpha);
        $this->assertSame('Alpha', Storage::disk('tenant')->get('hello.txt'));
    }

    public function test_the_disk_cannot_be_used_without_a_tenant(): void
    {
        $this->expectException(LogicException::class);

        Storage::disk('tenant');
    }

    public function test_the_disk_stops_working_after_forget(): void
    {
        $this->tenants->switchTo($this->alpha);
        Storage::disk('tenant')->put('hello.txt', 'Alpha');

        $this->tenants->forget();

        $this->expectException(LogicException::class);
        Storage::disk('tenant');
    }
}
