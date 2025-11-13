<?php

declare(strict_types=1);

namespace App\Core\Filesystem;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class FileUploader
{
    public function __construct(
        private FilesystemOperator $uploadsFilesystem,
        private SluggerInterface $slugger,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function upload(UploadedFile $file, string $targetDir): string
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $safeName = $this->slugger->slug($originalName);
        $fileName = $safeName . '-' . uniqid() . '.' . $file->guessExtension();

        $stream = fopen($file->getPathname(), 'r');

        $this->uploadsFilesystem->writeStream($targetDir . '/' . $fileName, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        return $fileName;
    }
}
