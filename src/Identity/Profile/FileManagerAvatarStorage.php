<?php

declare(strict_types=1);

namespace App\Identity\Profile;

use Nubit\Platform\Filesystem\FileManager;
use Override;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Photos go to the tenant's own prefix of the existing bucket, under `avatars/`. */
final readonly class FileManagerAvatarStorage implements AvatarStorage
{
    private const string DIRECTORY = 'avatars';

    public function __construct(
        private FileManager $files,
    ) {}

    #[Override]
    public function store(UploadedFile $file): string
    {
        return $this->files->upload($file, self::DIRECTORY);
    }

    #[Override]
    public function read(string $path): string
    {
        return $this->files->read(self::DIRECTORY . '/' . $path);
    }

    #[Override]
    public function delete(string $path): void
    {
        $location = self::DIRECTORY . '/' . $path;
        if ($this->files->exists($location)) {
            $this->files->delete($location);
        }
    }
}
