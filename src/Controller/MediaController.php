<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Base\BaseController;
use App\Core\Context\TenantContext;
use App\Core\Filesystem\FileUploader;
use App\Core\Util\Constants;
use App\Entity\Media;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class MediaController extends BaseController
{
    public function __invoke(
        Request $request,
        FileUploader $fileUploader,
        TenantContext $tenantContext,
    ): Media {
        /** @var ?UploadedFile $file */
        $file = $request->files->get('file');
        if (!$file) {
            throw new BadRequestHttpException('"file" is required');
        }

        $targetDir = Constants::IMAGE_UPLOAD_DIR . $tenantContext->getTenantName();

        $mediaObject = new Media();
        $mediaObject->setPath($fileUploader->upload($file, $targetDir));

        return $mediaObject;
    }
}
