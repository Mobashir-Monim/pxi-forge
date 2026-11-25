<?php

namespace App\Services\ObjectStore\Providers;

/**
 * The public disk, served by the app at /storage after `php artisan storage:link`. For local dev, where there is no bucket.
 * ponytail: local disk is lost on hosts with ephemeral filesystems, use s3 there.
 */
final class LocalObjectStore extends S3ObjectStore
{
    protected string $disk = 'public';
}
