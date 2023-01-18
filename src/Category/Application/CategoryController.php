<?php

namespace App\Category\Application;

use App\Base\BaseController;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\FileUploader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CategoryController extends BaseController
{
    private FileUploader $fileUploader;

    public function __construct(FileUploader $fileUploader)
    {
        parent::__construct();
        $this->fileUploader = $fileUploader;
    }

    #[Route('/category', name: 'category_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('category/index.html.twig');
    }

    private function uploadImage(Request $request): void
    {
        $imageFile = $request->files->get('imagen');
        if ($imageFile) {
            $image = $this->fileUploader->upload($imageFile, Constants::IMAGE_UPLOAD_DIR);
            $request->request->set('imagen', $image);
        }
    }
}
