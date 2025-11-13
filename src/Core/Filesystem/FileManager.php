<?php

declare(strict_types=1);

namespace App\Core\Filesystem;

use League\Flysystem\DirectoryListing;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;

final readonly class FileManager
{
    public function __construct(
        private FilesystemOperator $uploadsFilesystem,
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

    /**
     * @throws FilesystemException
     */
    public function delete(string $fileName): void
    {
        $this->uploadsFilesystem->delete($fileName);
    }

    /**
     * @return DirectoryListing<StorageAttributes>
     *
     * @throws FilesystemException
     */
    public function listContents(string $directory = ''): DirectoryListing
    {
        return $this->uploadsFilesystem->listContents($directory);
    }
}
