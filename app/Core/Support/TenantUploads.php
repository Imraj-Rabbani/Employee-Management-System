<?php

namespace App\Core\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Saves, downloads and deletes the current company's uploaded files.
 *
 *     $path = app(TenantUploads::class)->store($request->file('photo'), 'employees/photos');
 *     $employee->update(['photo' => $path]);   // e.g. "employees/photos/3fX9...k2.jpg"
 *
 * Works the same with a Livewire upload (TemporaryUploadedFile is an UploadedFile).
 * Files live on Storage::disk('tenant'), i.e. storage/app/tenants/{code}/...
 * Always save the returned path in the database, never a full folder path.
 */
class TenantUploads
{
    /**
     * Save the file in $folder under a random name (keeps the extension) and return its path.
     * Validate type and size in the form request / Livewire rules before calling this.
     */
    public function store(UploadedFile $file, string $folder): string
    {
        $path = $file->store($this->safeFolder($folder), 'tenant');

        if ($path === false) {
            throw new \RuntimeException("Could not save the uploaded file in [{$folder}].");
        }

        return $path;
    }

    /**
     * Send the file to the browser as a download. $name is what the user sees,
     * e.g. the original file name you saved next to the path.
     */
    public function download(string $path, ?string $name = null): StreamedResponse
    {
        return $this->disk()->download($path, $name);
    }

    /**
     * Send the file to be SHOWN in the page (e.g. <img src>), not saved. Check permission before calling.
     * private + 1 hour: the browser may cache it, shared proxies may not.
     */
    public function show(string $path): StreamedResponse
    {
        return $this->disk()->response($path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    /** Delete the file. Does nothing for an empty path (e.g. no photo was ever uploaded). */
    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            $this->disk()->delete($path);
        }
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    private function disk(): Filesystem
    {
        return Storage::disk('tenant');
    }

    /** Only simple relative folders like "employees/photos": no "..", no leading "/". */
    private function safeFolder(string $folder): string
    {
        $folder = trim($folder, '/');

        if ($folder === '' || ! preg_match('#^[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*$#', $folder)) {
            throw new InvalidArgumentException("Upload folder [{$folder}] is not allowed. Use letters, numbers, - and _ only.");
        }

        return $folder;
    }
}
