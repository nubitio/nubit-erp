<?php

namespace App\Product\Application;

use App\Base\BaseController;
use App\Core\Infrastructure\Service\FileUploader;
use App\Product\Domain\Product;
use App\Product\Domain\ProductCreateUseCase;
use App\Product\Domain\ProductDeleteUseCase;
use App\Product\Domain\ProductGetAllUseCase;
use App\Product\Domain\ProductUpdateUseCase;
use App\Core\Domain\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends BaseController
{
    private FileUploader $fileUploader;

    public function __construct(FileUploader $fileUploader)
    {
        parent::__construct();
        $this->fileUploader = $fileUploader;
    }

    #[Route('/product', name: 'product_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('product/index.html.twig');
    }

    #[Route('/product/get', name: 'product_list', methods: ['POST'])]
    public function getAll(ProductGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase());
    }

    #[Route('/product/add', name: 'product_create', methods: ['POST'])]
    public function add(Request $request, ProductCreateUseCase $createUseCase): Response
    {
        $this->uploadImage($request);
        /**
         * @var Product $product
         */
        $product = $this->serializer->deserialize(json_encode($request->request->all()), Product::class, 'json');
        $createUseCase($product);

        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/product/edit/{id}', name: 'product_update', methods: ['PUT', 'POST'])]
    public function edit(Request $request, ProductUpdateUseCase $updateUseCase, $id): Response
    {
        $this->uploadImage($request);
        $request->request->add(['producto_id' => $id]);
        /**
         * @var Product $product
         */
        $product = $this->serializer->deserialize(json_encode($request->request->all()), Product::class, 'json');
        $updateUseCase($product);

        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/product/delete/{id}', name: 'product_delete', methods: ['DELETE', 'POST'])]
    public function delete(ProductDeleteUseCase $deleteUseCase, $id): Response
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
