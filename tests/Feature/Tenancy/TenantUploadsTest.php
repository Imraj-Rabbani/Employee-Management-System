<?php

namespace Tests\Feature\Tenancy;

use App\Core\Support\TenantUploads;
use App\Core\Tenancy\TenantManager;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Task 2.5: the upload helper saves into the current company's folder.
 */
class TenantUploadsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $alpha;

    private TenantUploads $uploads;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alpha = Tenant::factory()->create(['code' => 'tup'.strtolower(str()->random(8))]);
        app(TenantManager::class)->switchTo($this->alpha);
        $this->uploads = app(TenantUploads::class);
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->forget();
        File::deleteDirectory(storage_path('app/tenants/'.$this->alpha->code));

        parent::tearDown();
    }

    public function test_it_stores_the_file_in_the_tenant_folder_under_a_random_name(): void
    {
        $path = $this->uploads->store(UploadedFile::fake()->image('my photo.jpg'), 'employees/photos');

        $this->assertStringStartsWith('employees/photos/', $path);
        $this->assertStringEndsWith('.jpg', $path);
        $this->assertStringNotContainsString('my photo', $path);
        $this->assertFileExists(storage_path("app/tenants/{$this->alpha->code}/{$path}"));
    }

    public function test_two_files_with_the_same_name_do_not_overwrite_each_other(): void
    {
        $first = $this->uploads->store(UploadedFile::fake()->create('cv.pdf', 10), 'cvs');
        $second = $this->uploads->store(UploadedFile::fake()->create('cv.pdf', 10), 'cvs');

        $this->assertNotSame($first, $second);
        $this->assertTrue($this->uploads->exists($first));
        $this->assertTrue($this->uploads->exists($second));
    }

    public function test_it_downloads_with_the_name_the_user_should_see(): void
    {
        $path = $this->uploads->store(UploadedFile::fake()->create('cv.pdf', 10), 'cvs');

        $response = $this->uploads->download($path, 'Rahim CV.pdf');

        $this->assertStringContainsString('Rahim CV.pdf', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_it_deletes_files_and_ignores_empty_paths(): void
    {
        $path = $this->uploads->store(UploadedFile::fake()->create('cv.pdf', 10), 'cvs');

        $this->uploads->delete($path);
        $this->uploads->delete(null);
        $this->uploads->delete('');

        $this->assertFalse(Storage::disk('tenant')->exists($path));
    }

    public function test_it_refuses_folders_that_could_escape_the_tenant_folder(): void
    {
        foreach (['../other-company', 'cvs/../../x', '', '/', 'a b'] as $folder) {
            try {
                $this->uploads->store(UploadedFile::fake()->create('cv.pdf', 10), $folder);
                $this->fail("Folder [{$folder}] should have been refused.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
