<?php

namespace App\Api;

use App\Base\BaseController;
use App\Sale\Domain\SaleInvoiceUseCase;
use Firebase\JWT\JWT;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiController extends BaseController
{
    /**
     * @param array<string, string|bool|int|float|null> $body
     */
    public function __construct(
        private readonly Request $request,
        private array $body,
        private readonly ApiRepository $apiRepository,
    ) {
        parent::__construct();
        /** @var array<string, string|bool|int|float|null> $body */
        $body = json_decode($this->request->getContent(), true);

        $this->body = $body;
    }

    #[Route('/api/auth/login', name: 'api_login', methods: ['POST'])]
    public function login(): Response
    {
        $key = 'YSFEeBPAo1MyOsOleKoVtasDl';
        $user = $this->apiRepository->getUserByName(strval($this->body['username']));

        if (!$user) {
            return $this->json(['error' => 'Authentication failed'], Response::HTTP_FORBIDDEN);
        } else {
            if (1 == $user['estado'] && password_verify(strval($this->body['password']), strval($user['password']))) {
                $data = [
                    'iat' => time(),
                    'exp' => time() + (60 * 60),
                    'sub' => $user['usuario_id'],
                    'name' => $user['username'],
                    'role' => $user['rol_id'],
                ];
                $token = JWT::encode($data, $key, 'HS256');

                return $this->json(['token' => $token]);
            } else {
                return $this->json(['error' => 'Authentication failed'], Response::HTTP_FORBIDDEN);
            }
        }
    }

    public function getConfig(): Response
    {
        return $this->json($this->apiRepository->getConfig());
    }

    public function getAllLocals(): Response
    {
        return $this->json($this->apiRepository->getAllLocals());
    }

    // Categories
    #[Route('/api/categories', name: 'api_getAllCategory', methods: ['GET'])]
    public function getAllCategories(): Response
    {
        return $this->json($this->apiRepository->getAllCategories());
    }

    #[Route('/api/categories/{id}', name: 'api_getOneCategory', methods: ['GET'])]
    public function getOneCategory(string $id): Response
    {
        $result = $this->apiRepository->getOneCategory($id);
        if ($result) {
            return $this->json($result);
        } else {
            return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/api/categories/{id}', name: 'api_createCategory', methods: ['POST'])]
    public function createCategory(string $id): Response
    {
        $result = $this->apiRepository->createCategory($this->body);
        if ($result['success']) {
            return $this->json($result['data'], Response::HTTP_CREATED);
        } else {
            return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/categories/{id}', name: 'api_updateCategory', methods: ['PUT'])]
    public function updateCategory(string $id): Response
    {
        $result = $this->apiRepository->updateCategory($id, $this->body);
        if ($result['success']) {
            return $this->json($result['data'], Response::HTTP_OK);
        } else {
            return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/categories/{id}', name: 'api_deleteCategory', methods: ['DELETE'])]
    public function deleteCategory(string $id): Response
    {
        $result = $this->apiRepository->deleteCategory($id);
        if ($result['success']) {
            return $this->json([], Response::HTTP_OK);
        } else {
            return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/image/category/{id}', name: 'api_uploadImageCategory', methods: ['PUT'])]
    public function uploadImageCategory(string $id): Response
    {
        return $this->json(['success' => true, 'message' => 'Image uploaded'], Response::HTTP_OK);
    }

    // Customer
    #[Route('/api/customers', name: 'api_getAllCustomer', methods: ['GET'])]
    public function getAllCustomer(): Response
    {
        return $this->json($this->apiRepository->getAllCustomers(), Response::HTTP_OK);
    }

    #[Route('/api/customers/{id}', name: 'api_getOneCustomer', methods: ['GET'])]
    public function getOneCustomer(string $id): Response
    {
        $result = $this->apiRepository->getOneCustomer($id);
        if ($result) {
            return $this->json($result);
        } else {
            return $this->json(['error' => 'Customer not found'], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/api/customers', name: 'api_createCustomer', methods: ['POST'])]
    public function createCustomer(): Response
    {
        $result = $this->apiRepository->createCustomer($this->body);
        if ($result['success']) {
            return $this->json($result['data'], Response::HTTP_CREATED);
        } else {
            return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/customers/{id} ', name: 'api-updateCustomer', methods: ['PUT'])]
    public function updateCustomer(string $id): Response
    {
        $result = $this->apiRepository->updateCustomer($id, $this->body);
        if ($result['success']) {
            return $this->json($result['data'], Response::HTTP_OK);
        } else {
            return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/customers/{id}', name: 'api_deleteCustomer', methods: ['DELETE'])]
    public function deleteCustomer(string $id): Response
    {
        $result = $this->apiRepository->deleteCustomer($id);
        if ($result['success']) {
            return $this->json([], Response::HTTP_OK);
        } else {
            return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/customers/{id}/image/upload', name: 'api_uploadImageCustomer', methods: ['POST'])]
    public function uploadImageCustomer(string $id): Response
    {
        return $this->json(['success' => true, 'message' => 'Image uploaded'], Response::HTTP_OK);
    }

    // Product
    #[Route('/api/product/{local_id}', name: 'api_getAllProducts', methods: ['GET'])]
    public function getAllProducts(string $local_id): Response
    {
        return $this->json($this->apiRepository->getAllProducts());
    }

    #[Route(' /api/products/{id}', name: 'api_getOneProduct', methods: ['GET'])]
    public function getOneProduct(string $id): Response
    {
        $result = $this->apiRepository->getOneProduct($id);
        if ($result) {
            return $this->json($result);
        } else {
            return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/api/products/{local_id}', name: 'api_createProduct', methods: ['POST'])]
    public function createProduct(string $local_id): Response
    {
        $result = $this->apiRepository->createProduct($local_id, $this->body);

        if ($result['success']) {
            return $this->json($result['data'], Response::HTTP_CREATED);
        } else {
            return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/products/{id}/{local_id}', name: 'api_updateProduct', methods: ['PUT'])]
    public function updateProduct(string $id, string $local_id): Response
    {
        $result = $this->apiRepository->updateProduct($local_id, $id, $this->body);

        if ($result['success']) {
            return $this->json($result['data'], Response::HTTP_OK);
        } else {
            return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/products/{id}', name: 'api_deleteProduct', methods: ['DELETE'])]
    public function deleteProduct(string $id): Response
    {
        $result = $this->apiRepository->deleteProduct($id);
        if ($result['success']) {
            return $this->json([], Response::HTTP_OK);
        } else {
            return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function uploadImage(): Response
    {
        return $this->json(['success' => true, 'message' => 'Image uploaded'], Response::HTTP_OK);
    }

    // Sales
    #[Route('/api/sales/{local_id}', name: 'api_getAllSales', methods: ['GET'])]
    public function getAllSales(string $local_id): Response
    {
        return $this->json($this->apiRepository->getAllSales($local_id));
    }

    #[Route('/api/sale/{id}', name: 'api_getOneSale', methods: ['GET'])]
    public function getOneSale(string $id): Response
    {
        $result = $this->apiRepository->getOneSale($id);
        if ($result) {
            return $this->json($result);
        } else {
            return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/api/sales', name: 'api_createSale', methods: ['POST'])]
    public function createSale(SaleInvoiceUseCase $saleInvoiceUseCase): Response
    {
        $result = $this->apiRepository->createSale($this->body);
        if ($result['success']) {
            if (1 == $this->body['has_invoice']) {
                /** @var array<string, mixed> $data */
                $data = $result['data'];
                /** @var string $saleId */
                $saleId = $data['movimiento_id'];
                $saleInvoiceUseCase($saleId);
            }

            return $this->json($result['data'], Response::HTTP_CREATED);
        } else {
            return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/sales/{id}', name: 'api_updateSale', methods: ['PUT'])]
    public function updateSale(string $id, SaleInvoiceUseCase $saleInvoiceUseCase): Response
    {
        $result = $this->apiRepository->updateSale($id, $this->body);
        if ($result['success']) {
            if (1 == $this->body['has_invoice'] && '' == $this->body['iserie']) {
                $saleInvoiceUseCase($id);
            }

            return $this->json($result['data'], Response::HTTP_OK);
        } else {
            return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/sales/{id}', name: 'api_deleteSale', methods: ['DELETE'])]
    public function deleteSale(string $id): Response
    {
        $result = $this->apiRepository->deleteSale($id);
        if ($result['success']) {
            return $this->json([], Response::HTTP_OK);
        } else {
            return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getSalesByDate(string $date): Response
    {
        $data = $this->apiRepository->getSalesByDate($date);

        if (null == $data) {
            return $this->json(0);
        }

        return $this->json($data['total']);
    }

    public function getSalesByDayForWeek(string $date): Response
    {
        $data = $this->apiRepository->getSalesByDayForWeek($date);

        if (null == $data) {
            return $this->json(0);
        }

        return $this->json($data['total']);
    }

    public function getWeeklySales(string $date): Response
    {
        $data = $this->apiRepository->getWeeklySales();

        return $this->json($data);
    }

    public function getWeeklySalesSoles(string $date, string $local_id): Response
    {
        return $this->json($this->apiRepository->getWeeklySalesSoles($local_id));
    }

    public function getMonthSalesSoles(string $date, string $local_id): Response
    {
        return $this->json($this->apiRepository->getMonthSalesSoles($local_id));
    }

    public function getMonthSales(string $date): Response
    {
        $data = $this->apiRepository->getMonthSales();

        return $this->json($data);
    }

    public function getTopProduct(string $date): Response
    {
        $data = $this->apiRepository->getTopProduct();

        return $this->json($data);
    }

    public function getVoucherForMonth(string $local_id): Response
    {
        return $this->json($this->apiRepository->getVoucherForMonth($local_id));
    }

    public function getVoucherForMonthSoles(string $local_id): Response
    {
        return $this->json($this->apiRepository->getVoucherForMonthSoles($local_id));
    }

    // Customer
    #[Route('/api/image/customer/{ruc}', name: 'api_getImageCustomer', methods: ['GET'])]
    public function getImageCustomer(string $id): void
    {
        $data = $this->apiRepository->getImageCustomer($id);
        header('Location: https://ui-avatars.com/api/?name='.$data[0]['nombre']);
    }

    #[Route('/api/image/product/{id}', name: 'api_getImageProduct', methods: ['GET'])]
    public function getImageProduct(string $id): void
    {
        $data = $this->apiRepository->getImageProduct($id);
        header('Location: https://ui-avatars.com/api/?name='.$data[0]['nombre']);
    }

    #[Route('/api/image/category/{id}', name: 'api_getImageCategory', methods: ['GET'])]
    public function getImageCategory(string $id): void
    {
        $data = $this->apiRepository->getImageCategory($id);
        header('Location: https://ui-avatars.com/api/?name='.$data[0]['nombre']);
    }
}
