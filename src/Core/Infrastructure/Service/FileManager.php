<?php

namespace App\Core\Infrastructure\Service;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;

final readonly class FileManager
{
    public function __construct(
        private FilesystemOperator $uploadsFilesystem
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function write(string $location, string $content): void
    {
        $this->uploadsFilesystem->write($location, $content);
    }

    /**
     * @throws FilesystemException
     */
    public function read(string $fileName): string
    {
        return $this->uploadsFilesystem->read($fileName);
    }

    /**
     * @throws FilesystemException
     */
    public function exists(string $fileName): bool
    {
        return $this->uploadsFilesystem->fileExists($fileName);
    }
}
