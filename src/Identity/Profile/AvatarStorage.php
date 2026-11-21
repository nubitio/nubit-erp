<?php

declare(strict_types=1);

namespace App\Identity\Profile;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Where profile photos live. A seam so the service can be tested without a bucket. */
interface AvatarStorage
{
    /** Writes the file and returns the path to keep on the user. */
    public function store(UploadedFile $file): string;

    public function read(string $path): string;

    public function delete(string $path): void;
}
