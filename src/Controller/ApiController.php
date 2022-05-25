<?php

namespace App\Controller;

use App\Domain\Product\Product;
use App\Domain\Product\UpdateProduct;
use App\Model\ApiModel;
use App\Model\BotModel;
use App\Model\UsuarioModel;
use Firebase\JWT\JWT;

class ApiController extends BaseController
{
    private $body;
    private UsuarioModel $userDao;
    private ApiModel $apiDao;
    private BotModel $botModel;
    private EFactController $efact;
    private UpdateProduct $updateProduct;

    public function __construct()
    {
        parent::__construct();
        $this->body = json_decode($this->app->get('BODY'), true);
        $this->userDao = new UsuarioModel();
        $this->apiDao = new ApiModel();
        $this->botModel = new BotModel();
        $this->efact = new EFactController();
        $this->updateProduct = $this->container->get(UpdateProduct::class);
    }

    public function beforeroute()
    {
        //public api
    }

    public function login()
    {
        $key = $this->app->get('SECRET');
        $user = $this->userDao->getByName($this->body['username']);

        if (!$user) {
            $this->app->error(403, 'Authentication failed');
        } else {
            if ($user['estado'] == 1 && password_verify($this->body['password'], $user['password'])) {
                $data = [
                    'iat'  => time(),
                    'exp'  => time() + (60 * 60),
                    'sub'  => $user['usuario_id'],
                    'name' => $user['username'],
                    'role' => $user['rol_id']
                ];
                $token = JWT::encode($data, $key);
                //$tokenData = JWT::decode($jwt, $key, array('HS256'));
                $this->json(['token' => $token]);
            } else {
                $this->app->status(403);
                $this->json(['error' => 'Authentication failed']);
            }
        }
    }

    public function getConfig()
    {
        $this->json($this->apiDao->getConfig());
    }

    public function getAllLocals()
    {
        $this->json($this->apiDao->getAllLocals());
    }

    public function getAllCategories()
    {
        $this->json($this->apiDao->getAllCategories());
    }

    public function getOneCategory()
    {
        $id = $this->app->get('PARAMS.id');
        $result = $this->apiDao->getOneCategory($id);
        if ($result) {
            $this->json($result);
        } else {
            $this->app->status(404);
            $this->json(['error' => 'Category not found']);
        }
    }

    public function createCategory()
    {
        $result = $this->apiDao->createCategory($this->body);
        if ($result['success']) {
            $this->app->status(201);
            $this->json($result['data']);
        } else {
            $this->app->status(500);
            $this->json(['error' => $result['msg']]);
        }
    }

    public function updateCategory()
    {
        $id = $this->app->get('PARAMS.id');
        $result = $this->apiDao->updateCategory($id, $this->body);
        if ($result['success']) {
            $this->app->status(200);
            $this->json($result['data']);
        } else {
            $this->app->status(500);
            $this->json(['error' => $result['msg']]);
        }
    }

    public function deleteCategory()
    {
        $id = $this->app->get('PARAMS.id');
        $result = $this->apiDao->deleteCategory($id);
        if ($result['success']) {
            $this->app->status(200);
            $this->json([]);
        } else {
            $this->app->status(500);
            $this->json(['error' => $result['msg']]);
        }
    }

    public function getAllCustomer()
    {
        $this->json($this->apiDao->getAllCustomers());
    }

    public function getOneCustomer()
    {
        $id = $this->app->get('PARAMS.id');
        $result = $this->apiDao->getOneCustomer($id);
        if ($result) {
            $this->json($result);
        } else {
            $this->app->status(404);
            $this->json(['error' => 'Customer not found']);
        }
    }

    public function createCustomer()
    {
        $result = $this->apiDao->createCustomer($this->body);
        if ($result['success']) {
            $this->app->status(201);
            $this->json($result['data']);
        } else {
            $this->app->status(500);
            $this->json(['error' => $result['msg']]);
        }
    }

    public function updateCustomer()
    {
        $id = $this->app->get('PARAMS.id');
        $result = $this->apiDao->updateCustomer($id, $this->body);
        if ($result['success']) {
            $this->app->status(200);
            $this->json($result['data']);
        } else {
            $this->app->status(500);
            $this->json(['error' => $result['msg']]);
        }
    }

    public function deleteCustomer()
    {
        $id = $this->app->get('PARAMS.id');
        $result = $this->apiDao->deleteCustomer($id);
        if ($result['success']) {
            $this->app->status(200);
            $this->json([]);
        } else {
            $this->app->status(500);
            $this->json(['error' => $result['msg']]);
        }
    }

    public function getAllProducts()
    {
        $this->json($this->apiDao->getAllProducts());
    }

    public function getOneProduct()
    {
        $id = $this->app->get('PARAMS.id');
        $result = $this->apiDao->getOneProduct($id);
        if ($result) {
            $this->json($result);
        } else {
            $this->app->status(404);
            $this->json(['error' => 'Category not found']);
        }
    }

    public function createProduct()
    {
        $result = $this->apiDao->createProduct($this->body);
        if ($result['success']) {
            $this->app->status(201);
            $this->json($result['data']);
        } else {
            $this->app->status(500);
            $this->json(['error' => $result['msg']]);
        }
    }

    public function updateProduct()
    {
        $id = $this->app->get('PARAMS.id');
        $result = $this->apiDao->updateProduct($id, $this->body);
        if ($result['success']) {
            $this->app->status(200);
            $this->json($result['data']);
        } else {
            $this->app->status(500);
            $this->json(['error' => $result['msg']]);
        }
    }

    public function deleteProduct()
    {
        $id = $this->app->get('PARAMS.id');
        $result = $this->apiDao->deleteProduct($id);
        if ($result['success']) {
            $this->app->status(200);
            $this->json([]);
        } else {
            $this->app->status(500);
            $this->json(['error' => $result['msg']]);
        }
    }

    public function uploadImage()
    {
        $_POST['producto_id'] = $this->app->get('PARAMS.id');
        if (!empty($_FILES['imagen']['name'])) {
            $_POST['imagen'] = $this->uploader->uploadFile($_FILES['imagen'], 'uploads/images');
        }

        /**
         * @var Product $product
         */
        $product = $this->serializer->deserialize(json_encode($_POST), Product::class, 'json');
        $this->updateProduct->execute($product);
        $this->app->status(200);
        $this->json(['success' => true, 'message' => 'Image uploaded']);
    }

    public function getAllSales()
    {
        $local_id = $this->app->get('PARAMS.local_id');
        $this->json($this->apiDao->getAllSales($local_id));
    }

    public function getOneSale()
    {
        $id = $this->app->get('PARAMS.id');
        $result = $this->apiDao->getOneSale($id);
        if ($result) {
            $this->json($result);
        } else {
            $this->app->status(404);
            $this->json(['error' => 'Category not found']);
        }
    }

    public function createSale()
    {
        $result = $this->apiDao->createSale($this->body);
        if ($result['success']) {
            if ($this->body['has_invoice'] == 1) {
                $this->makeInvoice($result['data']['movimiento_id'], $this->body['local_id']);
            }
            $this->app->status(201);
            $this->json($result['data']);
        } else {
            $this->app->status(500);
            $this->json(['error' => $result['msg']]);
        }
    }

    public function updateSale()
    {
        $id = $this->app->get('PARAMS.id');
        $result = $this->apiDao->updateSale($id, $this->body);
        if ($result['success']) {
            if ($this->body['has_invoice'] == 1 && $this->body['iserie'] == "") {
                $this->makeInvoice($id, $this->body['local_id']);
            }
            $this->app->status(200);
            $this->json($result['data']);
        } else {
            $this->app->status(500);
            $this->json(['error' => $result['msg']]);
        }
    }

    public function deleteSale()
    {
        $id = $this->app->get('PARAMS.id');
        $result = $this->apiDao->deleteSale($id);
        if ($result['success']) {
            $this->app->status(200);
            $this->json([]);
        } else {
            $this->app->status(500);
            $this->json(['error' => $result['msg']]);
        }
    }

    function makeInvoice($id, $local_id): array
    {
        $result = $this->apiDao->makeInvoice($id, $local_id);
        $sale = $this->botModel->getOne($id);
        if ($result['success']) {
            if ($sale['success']) {
                $info = $this->efact->makeInvoice($sale['data']);
                $this->botModel->updateSaleState($info);
            }
        }
        return $result;
    }

    public function getSalesByDate()
    {
        $date = $this->app->get('PARAMS.date');
        //dd($this->apiDao->getWeeklySales());
        $data = $this->apiDao->getSalesByDate($date);
        $this->json($data["total"]);

    }

    public function getSalesByDayForWeek()
    {
        $date = $this->app->get('PARAMS.date');
        $data = $this->apiDao->getSalesByDayForWeek($date);
        $this->json($data["total"]);

    }

    public function getWeeklySales()
    {
        $date = $this->app->get('PARAMS.date');
        $data = $this->apiDao->getWeeklySales();
        $this->json($data);
    }

    public function getWeeklySalesSoles()
    {
        $date = $this->app->get('PARAMS.date');
        $local_id = $this->app->get('PARAMS.local_id');
        $this->json($this->apiDao->getWeeklySalesSoles($local_id));

    }

    public function getMonthSalesSoles()
    {
        $date = $this->app->get('PARAMS.date');
        $local_id = $this->app->get('PARAMS.local_id');
        $this->json($this->apiDao->getMonthSalesSoles($local_id));

    }

    public function getMonthSales()
    {
        $date = $this->app->get('PARAMS.date');
        $data = $this->apiDao->getMonthSales();
        $this->json($data);
    }

    public function getTopProduct()
    {
        $date = $this->app->get('PARAMS.date');
        $data = $this->apiDao->getTopProduct();
        $this->json($data);
    }

    public function getVoucherForMonth()
    {
        $local_id = $this->app->get('PARAMS.local_id');
        $this->json($this->apiDao->getVoucherForMonth($local_id));

    }

    public function getVoucherForMonthSoles()
    {
        $local_id = $this->app->get('PARAMS.local_id');
        $this->json($this->apiDao->getVoucherForMonthSoles($local_id));
    }

    public function getUrlCustomer()
    {
        $this->json($this->apiDao->getUrlCustomer());
    }

    public function getUrlProduct()
    {
        $this->json($this->apiDao->getUrlProduct());
    }
}