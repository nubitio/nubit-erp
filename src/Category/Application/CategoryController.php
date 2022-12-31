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

    #[Route('/category/add', name: 'category_create', methods: ['POST'])]
    public function add(Request $request, CategoryCreateUseCase $createUseCase): Response
    {
        $this->uploadImage($request);
        /**
         * @var Category $category
         */
        $category = $this->serializer->deserialize(json_encode($request->request->all()), Category::class, 'json');
        $createUseCase($category);

        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/category/edit/{id}', name: 'category_update', methods: ['PUT', 'POST'])]
    public function edit(Request $request, CategoryUpdateUseCase $updateUseCase, $id): Response
    {
        $this->uploadImage($request);
        $request->request->add(['categoria_id' => $id]);
        /**
         * @var Category $category
         */
        $category = $this->serializer->deserialize(json_encode($request->request->all()), Category::class, 'json');
        $updateUseCase($category);

        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/category/delete/{id}', name: 'category_delete', methods: ['DELETE', 'POST'])]
    public function delete(CategoryDeleteUseCase $deleteUseCase, $id): Response
    {
        $deleteUseCase($id);

        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
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
