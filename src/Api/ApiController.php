<?php

namespace App\Api;

use Exception;
use App\Entity\Sale;
use App\Service\InvoiceCalculator;
use Symfony\Component\Uid\Uuid;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use App\Repository\InvoiceRepository;
use App\Repository\SaleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Firebase\JWT\JWT;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

class ApiController extends AbstractController
{
    /**
     * @var array<string, string|bool|int|float|null>
     */
    private array $body;

    public function __construct(
        private readonly RequestStack $request,
        private readonly ApiRepository $apiRepository,
        private readonly SaleRepository $saleRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        /** @var array<string, string|bool|int|float|null>|null $body */
        $body = json_decode($this->request->getCurrentRequest()?->getContent() ?? '[]', true);

        if (null === $body) {
            $body = [];
        }

        $this->body = $body;
    }

    #[Route('/api/auth/login', name: 'api_login', methods: ['POST'])]
    public function login(): Response
    {
        $key = 'YSFEeBPAo1MyOsOleKoVtasDl';
        $user = $this->apiRepository->getUserByName(strval($this->body['username']));

        if (!$user) {
            return $this->json(['error' => 'Authentication failed'], Response::HTTP_FORBIDDEN);
        }

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
        }

        return $this->json(['error' => 'Authentication failed'], Response::HTTP_FORBIDDEN);
    }

    #[Route('/api/config', name: 'api_getConfig', methods: ['GET'])]
    public function getConfig(): Response
    {
        return $this->json($this->apiRepository->getConfig());
    }

    #[Route('/api/locals', name: 'api_getAllLocals', methods: ['GET'])]
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
        }

        return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
    }

    #[Route('/api/categories', name: 'api_createCategory', methods: ['POST'])]
    public function createCategory(): Response
    {
        $result = $this->apiRepository->createCategory($this->body);
        if ($result['success']) {
            return $this->json($result['data'], Response::HTTP_CREATED);
        }

        return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route('/api/categories/{id}', name: 'api_updateCategory', methods: ['PUT'])]
    public function updateCategory(string $id): Response
    {
        $result = $this->apiRepository->updateCategory($id, $this->body);
        if ($result['success']) {
            return $this->json($result['data'], Response::HTTP_OK);
        }

        return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route('/api/categories/{id}', name: 'api_deleteCategory', methods: ['DELETE'])]
    public function deleteCategory(string $id): Response
    {
        $result = $this->apiRepository->deleteCategory($id);
        if ($result['success']) {
            return $this->json([], Response::HTTP_OK);
        }

        return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route('/api/image/category/{id}', name: 'api_uploadImageCategory', methods: ['PUT'])]
    public function uploadImageCategory(): Response
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
        }

        return $this->json(['error' => 'Customer not found'], Response::HTTP_NOT_FOUND);
    }

    #[Route('/api/customers', name: 'api_createCustomer', methods: ['POST'])]
    public function createCustomer(): Response
    {
        $result = $this->apiRepository->createCustomer($this->body);
        if ($result['success']) {
            return $this->json($result['data'], Response::HTTP_CREATED);
        }

        return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route('/api/customers/{id} ', name: 'api-updateCustomer', methods: ['PUT'])]
    public function updateCustomer(string $id): Response
    {
        $result = $this->apiRepository->updateCustomer($id, $this->body);
        if ($result['success']) {
            return $this->json($result['data'], Response::HTTP_OK);
        }

        return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route('/api/customers/{id}', name: 'api_deleteCustomer', methods: ['DELETE'])]
    public function deleteCustomer(string $id): Response
    {
        $result = $this->apiRepository->deleteCustomer($id);
        if ($result['success']) {
            return $this->json([], Response::HTTP_OK);
        }

        return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route('/api/customers/{id}/image/upload', name: 'api_uploadImageCustomer', methods: ['POST'])]
    public function uploadImageCustomer(): Response
    {
        return $this->json(['success' => true, 'message' => 'Image uploaded'], Response::HTTP_OK);
    }

    // Product
    #[Route('/api/product/{local_id}', name: 'api_getAllProducts', methods: ['GET'])]
    public function getAllProducts(): Response
    {
        return $this->json($this->apiRepository->getAllProducts());
    }

    #[Route(' /api/products/{id}', name: 'api_getOneProduct', methods: ['GET'])]
    public function getOneProduct(string $id): Response
    {
        $result = $this->apiRepository->getOneProduct($id);
        if ($result) {
            return $this->json($result);
        }

        return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
    }

    #[Route('/api/products/{local_id}', name: 'api_createProduct', methods: ['POST'])]
    public function createProduct(): Response
    {
        $result = $this->apiRepository->createProduct($this->body);

        if ($result['success']) {
            return $this->json($result['data'], Response::HTTP_CREATED);
        }

        return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route('/api/products/{id}/{local_id}', name: 'api_updateProduct', methods: ['PUT'])]
    public function updateProduct(string $id): Response
    {
        $result = $this->apiRepository->updateProduct($id, $this->body);

        if ($result['success']) {
            return $this->json($result['data'], Response::HTTP_OK);
        }

        return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route('/api/products/{id}', name: 'api_deleteProduct', methods: ['DELETE'])]
    public function deleteProduct(string $id): Response
    {
        $result = $this->apiRepository->deleteProduct($id);
        if ($result['success']) {
            return $this->json([], Response::HTTP_OK);
        }

        return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route('/api/products/{id}/image/upload', name: 'api_uploadImageProduct', methods: ['POST'])]
    public function uploadImage(): Response
    {
        return $this->json(['success' => true, 'message' => 'Image uploaded'], Response::HTTP_OK);
    }

    // Sales
    #[Route('/api/sales/{local_id}', name: 'api_getAllSales', methods: ['GET'])]
    public function getAllSales(string $local_id): Response
    {
        try {
            $data = $this->json($this->apiRepository->getAllSales($local_id));
        } catch (Exception) {
            $data = $this->json([]);
        }

        return $data;
    }

    #[Route('/api/sale/{id}', name: 'api_getOneSale', methods: ['GET'])]
    public function getOneSale(string $id): Response
    {
        try {
            $result = $this->apiRepository->getOneSale($id);
        } catch (Exception) {
            $result = null;
        }

        if ($result) {
            return $this->json($result);
        }

        return $this->json(['error' => 'Sale not found'], Response::HTTP_NOT_FOUND);
    }

    #[Route('/api/sales', name: 'api_createSale', methods: ['POST'])]
    public function createSale(): Response
    {
        $result = $this->apiRepository->createSale($this->body);
        if ($result['success']) {
            /** @var array<string, mixed> $data */
            $data = $result['data'];

            if (1 == $this->body['make_invoice']) {
                /** @var string $saleId */
                $saleId = $data['movimiento_id'];

                try {
                    $invoiceResult = $this->createInvoiceRecord($saleId);

                    $data['invoice_created'] = $invoiceResult['success'];
                    if ($invoiceResult['success']) {
                        $data['invoice_series'] = $invoiceResult['series'];
                        $data['invoice_number'] = $invoiceResult['number'];
                        $data['invoice_name'] = $invoiceResult['name'];
                    } else {
                        $data['invoice_error'] = $invoiceResult['error'];
                    }
                } catch (Exception $e) {
                    $data['invoice_created'] = false;
                    $data['invoice_error'] = $e->getMessage();
                }
            }

            return $this->json($data, Response::HTTP_CREATED);
        }

        return $this->json(['error' => $result['msg']], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route('/api/image/product/{id}', name: 'api_getImageProduct', methods: ['GET'])]
    public function getImageProduct(string $id): Response
    {
        try {
            $data = $this->apiRepository->getImageProduct($id);
        } catch (Exception) {
            $data = [['nombre' => 'No image']];
        }

        $imageUrl = 'https://ui-avatars.com/api/?name=' . $data[0]['nombre'];

        return new StreamedResponse(function () use ($imageUrl): void {
            readfile($imageUrl);
        });
    }

    /**
     * @return array<string, mixed>
     * @throws \Doctrine\DBAL\Exception
     */
    private function createInvoiceRecord(string $saleId): array
    {
        $sale = $this->saleRepository->find($saleId);

        if (!$sale instanceof Sale) {
            return ['success' => false, 'error' => 'Venta no encontrada.'];
        }

        if ($sale->isHasInvoice()) {
            return ['success' => false, 'error' => 'La venta ya tiene un comprobante emitido.'];
        }

        $localId = $this->entityManager->getConnection()->fetchOne(
            'select local_id from almacen where almacen_id = ?',
            [$sale->getAlmacen()?->getId()]
        );

        $invoiceCalculator = new InvoiceCalculator(
            floatval($sale->getSubtotal()),
            $sale->getDescuento() ?? 0.0,
            floatval($sale->getIgv()),
            $sale->getRecargoConsumo() ?? 0.0
        );
        $invoiceAmounts = $invoiceCalculator->calculate();
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $voucherType = strlen($sale->getCliente()?->getRuc() ?? '') === 11 ? '01' : '03';

            $existingInvoiceId = $connection->fetchOne(
                'SELECT comprobante_id FROM comprobante WHERE movimiento_id = :saleId FOR UPDATE',
                ['saleId' => $sale->getId()]
            );
            if ($existingInvoiceId) {
                $connection->commit();

                return ['success' => false, 'error' => 'La venta ya tiene un comprobante emitido.'];
            }

            $series = $connection->fetchOne(
                'SELECT serie
                 FROM local_comprobante
                 WHERE local_id = :localId
                   AND tipo_comprobante_id = :voucherType
                 LIMIT 1',
                ['localId' => $localId, 'voucherType' => $voucherType]
            );

            if (!$series) {
                $connection->rollBack();

                return ['success' => false, 'error' => 'No hay serie configurada para el tipo de comprobante.'];
            }

            $nextNumber = $connection->fetchOne(
                'SELECT IFNULL(MAX(CAST(numero AS SIGNED)), 0) + 1 FROM comprobante WHERE serie = :series FOR UPDATE',
                ['series' => $series]
            );

            $connection->insert('comprobante', [
                'comprobante_id' => Uuid::v4()->toRfc4122(),
                'raw_document_id' => null,
                'movimiento_id' => $sale->getId(),
                'fecha' => new DateTimeImmutable('now', new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                'tipo_comprobante_id' => $voucherType,
                'serie' => $series,
                'numero' => $nextNumber,
                'cliente_id' => $sale->getCliente()?->getRuc(),
                'inafecto' => 0,
                'subtotal' => $invoiceAmounts->getSubtotal(),
                'gravada' => $invoiceAmounts->getTaxedAmount(),
                'igv' => $invoiceAmounts->getTax(),
                'rc' => $invoiceAmounts->getChargesAmount(),
                'total' => $invoiceAmounts->getTotal(),
                'discount_percent' => $sale->getDescuento() ?? 0.0,
                'discount_amount' => $invoiceAmounts->getDiscountAmount(),
                'voided' => $sale->getEstado(),
                'created_at' => $sale->getDateCreated()?->format('Y-m-d H:i:s'),
            ]);

            $connection->commit();
        } catch (Throwable $throwable) {
            $connection->rollBack();
            throw $throwable;
        }

        $invoice = $this->invoiceRepository->findOneBy(['sale' => $saleId]);

        $sale->setHasInvoice(true);
        $invoiceName = ($invoice?->getSeries() ?? '') . '-' . ($invoice?->getNumber() ?? '');
        $sale->setInvoice($invoiceName);
        $sale->setTotal($invoice?->getTotal());

        $this->entityManager->persist($sale);
        $this->entityManager->flush();

        return [
            'success' => true,
            'series' => $invoice?->getSeries(),
            'number' => $invoice?->getNumber(),
            'name' => $invoiceName,
        ];
    }
}
