<?php

declare(strict_types=1);

namespace App\Media\Controller;

use App\Core\Base\BaseController;
use Nubit\Platform\Filesystem\FileManager;
use App\Efact\Config\EfactConstants as Constants;
use App\Media\Entity\Media;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class MediaController extends BaseController
{
    public function __invoke(
        Request $request,
        FileManager $fileManager,
    ): Media {
        /** @var ?UploadedFile $file */
        $file = $request->files->get('file');
        if (!$file) {
            throw new BadRequestHttpException('"file" is required');
        }

        $mediaObject = new Media();
        $mediaObject->setPath($fileManager->upload($file, Constants::IMAGE_DIR));

        return $mediaObject;
    }
}
