<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploader
{
    private SluggerInterface $slugger;

    public function __construct(SluggerInterface $slugger)
    {
        $this->slugger = $slugger;
    }

    /**
     * @throws \Exception
     */
    public function upload(UploadedFile $file, string $targetDir): string
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $safeName = $this->slugger->slug($originalName);
        $fileName = $safeName . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move($targetDir, $fileName);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        return $fileName;
    }
}