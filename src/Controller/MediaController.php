<?php

namespace App\Controller;

use App\Core\Base\BaseController;
use App\Core\Filesystem\FileUploader;
use App\Core\Util\Constants;
use App\Entity\Media;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class MediaController extends BaseController
{
    public function __invoke(Request $request, FileUploader $fileUploader): Media
    {
        /** @var ?UploadedFile $file */
        $file = $request->files->get('file');
        if (!$file) {
            throw new BadRequestHttpException('"file" is required');
        }

        $mediaObject = new Media();
        $mediaObject->setPath($fileUploader->upload($file, Constants::IMAGE_UPLOAD_DIR));

        return $mediaObject;
    }
}
