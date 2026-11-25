<?php

namespace App\Services\ObjectStore\Providers;

use App\Services\ObjectStore\Contracts\ObjectStore;
use App\Services\ObjectStore\Data\StoredObject;
use App\Services\ObjectStore\Exceptions\ObjectNotFound;
use App\Services\Shared\Exceptions\ProviderUnavailable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;
use Throwable;

/**
 * Any S3-compatible store (AWS S3, R2, Spaces, MinIO) through the `s3` disk; AWS_ENDPOINT picks which.
 * Relies on the disk's `throw`: without it a rejected write returns false and reads as success.
 */
class S3ObjectStore implements ObjectStore
{
    protected string $disk = 's3';

    private function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter */
        return Storage::disk($this->disk);
    }

    public function put(string $key, string $contents, ?string $mime = null): StoredObject
    {
        try {
            $this->disk()->put($key, $contents, $mime !== null ? ['ContentType' => $mime] : []);
        } catch (Throwable $e) {
            throw new ProviderUnavailable("Object store write failed [{$key}]: {$e->getMessage()}", previous: $e);
        }

        return new StoredObject(
            key: $key,
            url: $this->url($key),
            byteSize: strlen($contents),
            mime: $mime,
        );
    }

    public function get(string $key): string
    {
        if (! $this->exists($key)) {
            throw ObjectNotFound::forKey($key);
        }

        try {
            return (string) $this->disk()->get($key);
        } catch (Throwable $e) {
            throw new ProviderUnavailable("Object store read failed [{$key}]: {$e->getMessage()}", previous: $e);
        }
    }

    public function exists(string $key): bool
    {
        try {
            return $this->disk()->exists($key);
        } catch (Throwable $e) {
            throw new ProviderUnavailable("Object store stat failed [{$key}]: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * @return iterable<array{key: string, size: int}>
     */
    public function list(string $prefix): iterable
    {
        try {
            // The listing already carries each size; allFiles() drops it, and size() is then one HEAD request per object.
            foreach ($this->disk()->getDriver()->listContents($prefix, true) as $item) {
                if ($item instanceof FileAttributes) {
                    yield ['key' => $item->path(), 'size' => (int) $item->fileSize()];
                }
            }
        } catch (Throwable $e) {
            throw new ProviderUnavailable("Object store list failed [{$prefix}]: {$e->getMessage()}", previous: $e);
        }
    }

    public function url(string $key): string
    {
        return $this->disk()->url($key);
    }

    public function delete(string $key): void
    {
        try {
            $this->disk()->delete($key);
        } catch (Throwable $e) {
            throw new ProviderUnavailable("Object store delete failed [{$key}]: {$e->getMessage()}", previous: $e);
        }
    }
}
