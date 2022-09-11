<?php

namespace App\Category\Application;

use App\Base\BaseController;
use App\Category\Domain\Category;
use App\Category\Domain\CategoryCreateUseCase;
use App\Category\Domain\CategoryDeleteUseCase;
use App\Category\Domain\CategoryGetAllUseCase;
use App\Category\Domain\CategoryUpdateUseCase;
use App\Service\FileUploader;
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CategoryController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    #[Route('/category', name: 'category_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('category/index.html.twig');
    }

    #[Route('/category/get', name: 'category_list', methods: ['POST'])]
    public function getAll(): Response
    {
        $getAllUseCase = $this->container->get(CategoryGetAllUseCase::class);
        return $this->json($getAllUseCase());
    }

    #[Route('/category/add', name: 'category_create', methods: ['POST'])]
    public function add(Request $request): Response
    {
        $fileUploader = $this->container->get(FileUploader::class);
        $imageFile = $request->files->get('imagen');
        if ($imageFile) {
            $image = $fileUploader->upload($imageFile, Constants::IMAGE_UPLOAD_DIR);
            $request->request->set('imagen', $image);
        }
        /**
         * @var Category $category
         */
        $category = $this->serializer->deserialize(json_encode($request->request->all()), Category::class, 'json');
        $createUseCase = $this->container->get(CategoryCreateUseCase::class);
        $createUseCase($category);
        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/category/edit/{id}', name: 'category_update', methods: ['POST'])]
    public function edit(Request $request, $id): Response
    {
        $fileUploader = $this->container->get(FileUploader::class);
        $imageFile = $request->files->get('imagen');
        if ($imageFile) {
            $image = $fileUploader->upload($imageFile, Constants::IMAGE_UPLOAD_DIR);
            $request->request->set('imagen', $image);
        }
        $request->request->add(['categoria_id' => $id]);
        /**
         * @var Category $category
         */
        $category = $this->serializer->deserialize(json_encode($request->request->all()), Category::class, 'json');
        $updateUseCase = $this->container->get(CategoryUpdateUseCase::class);
        $updateUseCase($category);
        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/category/delete/{id}', name: 'category_delete', methods: ['POST'])]
    public function delete($id): Response
    {
        $deleteUseCase = $this->container->get(CategoryDeleteUseCase::class);
        $deleteUseCase($id);
        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
