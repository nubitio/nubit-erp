<?php

namespace App\Controller;

use App\Domain\Product\CreateProduct;
use App\Domain\Product\DeleteProduct;
use App\Domain\Product\GetAllProduct;
use App\Domain\Product\UpdateProduct;
use App\Model\ProductoModel;
use Template;

class ProductoController extends BaseController
{
    private ProductoModel $model;
    private GetAllProduct $getAllProduct;
    private CreateProduct $createProduct;
    private UpdateProduct $updateProduct;
    private DeleteProduct $deleteProduct;

    public function __construct(GetAllProduct $getAllProduct, CreateProduct $createProduct, UpdateProduct $updateProduct, DeleteProduct $deleteProduct)
    {
        parent::__construct();
        $this->model = new ProductoModel();
        $this->getAllProduct = $getAllProduct;
        $this->createProduct = $createProduct;
        $this->updateProduct = $updateProduct;
        $this->deleteProduct = $deleteProduct;
    }

    public function index()
    {
        $this->render('producto/index.html.twig');
    }

    public function getAll()
    {
        $this->json($this->getAllProduct->execute());
    }

    public function lista()
    {
        $filtered = (bool)$this->app->get('PARAMS.filtered');
        $extended = (bool)$this->app->get('PARAMS.extended');
        $data = $this->model->lista($filtered, $extended);
        $this->json($data);
    }

    public function listafull()
    {
        $data = $this->model->listafull();
        $this->json($data);
    }

    public function add()
    {
        $this->createProduct->execute($_POST);
        $this->json(['success' => true]);
    }

    public function edit()
    {
        $id = $this->app->get('PARAMS.id');
        $_POST['producto_id'] = $id;
        $this->updateProduct->execute($_POST);
        $this->json(['success' => true]);
    }

    public function delete()
    {
        $id = $this->app->get('PARAMS.id');
        $this->deleteProduct->execute($id);
        $this->json(['success' => true]);
    }

    public function exportar_productos()
    {
        $data = $this->model->exportar_productos();
        $extras = [
            'title'    => 'REPORTE DE PRODUCTOS',
            'subtitle' => ''
        ];
        $this->export($data, $extras);
    }
}
