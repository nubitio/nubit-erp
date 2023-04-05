<?php

namespace App\Media\Application;

use App\Base\BaseController;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\FileUploader;
use App\Media\Domain\Media;
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
        $mediaObject->path = $fileUploader->upload($file, Constants::IMAGE_UPLOAD_DIR);

        return $mediaObject;
    }
}
