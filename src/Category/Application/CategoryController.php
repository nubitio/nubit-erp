<?php

namespace App\Category\Application;

use App\Base\BaseController;
use App\Category\Domain\Category;
use App\Category\Domain\CategoryCreateUseCase;
use App\Category\Domain\CategoryDeleteUseCase;
use App\Category\Domain\CategoryGetAllUseCase;
use App\Category\Domain\CategoryUpdateUseCase;
use App\Core\Infrastructure\Service\FileUploader;
use App\Core\Domain\Util\Constants;
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

    #[Route('/category/get', name: 'category_list', methods: ['POST'])]
    public function getAll(CategoryGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase());
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
