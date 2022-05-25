<?php

namespace App\Controller;

use App\Domain\Product\CreateProduct;
use App\Domain\Product\DeleteProduct;
use App\Domain\Product\ExportProducts;
use App\Domain\Product\GetAllProducts;
use App\Domain\Product\GetProductList;
use App\Domain\Product\Product;
use App\Domain\Product\UpdateProduct;
use App\Util\Constants;
use Symfony\Component\Uid\Uuid;

class ProductoController extends BaseController
{
    private GetAllProducts $getAllProduct;
    private CreateProduct $createProduct;
    private UpdateProduct $updateProduct;
    private DeleteProduct $deleteProduct;
    private ExportProducts $exportProducts;
    private GetProductList $getProductList;

    public function __construct(
        GetAllProducts $getAllProduct,
        CreateProduct  $createProduct,
        UpdateProduct  $updateProduct,
        DeleteProduct  $deleteProduct,
        ExportProducts $exportProducts,
        GetProductList $getProductList
    )
    {
        parent::__construct();
        $this->getAllProduct = $getAllProduct;
        $this->createProduct = $createProduct;
        $this->updateProduct = $updateProduct;
        $this->deleteProduct = $deleteProduct;
        $this->exportProducts = $exportProducts;
        $this->getProductList = $getProductList;
    }

    public function index()
    {
        $this->render('producto/index.html.twig');
    }

    public function getAll()
    {
        $format = "local_id = '%s'";
        $filter = sprintf($format, $this->current_local_id);
        $this->json($this->getAllProduct->execute($filter));
    }

    public function lista()
    {
        $data = $this->getProductList->execute($this->current_local_id);
        $this->json($data);
    }

    public function listafull()
    {
        $data = $this->getProductList->execute($this->current_local_id);
        $this->json($data);
    }

    public function add()
    {
        $_POST['producto_id'] = Uuid::v4();
        if (!empty($_FILES['imagen']['name'])) {
            $_POST['imagen'] = $this->uploader->uploadFile($_FILES['imagen'], 'uploads/images');
        }
        /**
         * @var Product $product
         */
        $product = $this->serializer->deserialize(json_encode($_POST), Product::class, 'json');
        $this->createProduct->execute($product);
        $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    public function edit()
    {
        $id = $this->app->get('PARAMS.id');
        $_POST['producto_id'] = $id;
        if (!empty($_FILES['imagen']['name'])) {
            $_POST['imagen'] = $this->uploader->uploadFile($_FILES['imagen'], 'uploads/images');
        }
        /**
         * @var Product $product
         */
        $product = $this->serializer->deserialize(json_encode($_POST), Product::class, 'json');
        $this->updateProduct->execute($product);
        $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    public function delete()
    {
        $id = $this->app->get('PARAMS.id');
        $this->deleteProduct->execute($id);
        $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }

    public function exportar_productos()
    {
        $data = $this->exportProducts->execute($this->current_local_id);
        $extras = [
            'title'    => 'REPORTE DE PRODUCTOS',
            'subtitle' => ''
        ];
        $this->export($data, $extras);
    }
}
