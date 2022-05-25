<?php

namespace App\Service;

use Symfony\Component\String\Slugger\SluggerInterface;

class UploaderHelper
{
    private SluggerInterface $slugger;

    public function __construct(SluggerInterface $slugger)
    {
        $this->slugger = $slugger;
    }

    /**
     * @throws \Exception
     */
    public function uploadFile(array $file, string $targetDir): string
    {
        $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);

        $safeName = $this->slugger->slug($originalName);
        $newFileName = $safeName . '-' . uniqid() . '.' . $extension;

        if (move_uploaded_file($file['tmp_name'], $targetDir . '/' . $newFileName)) {
            return $newFileName;
        } else {
            throw new \Exception('Upload failed');
        }
    }
}