<?php

namespace App\Api;

use App\Base\BaseController;
use App\Core\Infrastructure\Service\MenuBuilder;
use App\Core\Infrastructure\Service\SettingsService;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ApiController extends BaseController
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    #[Route('/api/menu/{roleId}', name: 'api_menu')]
    public function getMenuForRole($roleId, MenuBuilder $menuBuilder): JsonResponse
    {
        $menu = $menuBuilder->buildMenuForRole($roleId);

        return $this->json($menu);
    }

    #[Route('/api/setting/{key}', name: 'api_setting_get', methods: ['GET'])]
    public function getSetting($key, SettingsService $settingsService): JsonResponse
    {
        $value = $settingsService->get($key);

        return $this->json([
            'key' => $key,
            'value' => $value,
        ]);
    }

    #[Route('/api/company', name: 'api_company_get', methods: ['GET'])]
    public function getCompany(): JsonResponse
    {
        $data = $this->connection->fetchAllAssociative('SELECT * FROM empresa where estado = 1');

        return $this->json($data);
    }

    #[Route('/api/local/{id}/warehouse', name: 'api_warehouse_get', methods: ['GET'])]
    public function getWarehouse($id): JsonResponse
    {
        $data = $this->connection->fetchAllAssociative('SELECT * FROM almacen where local_id = ? and estado = 1', [$id]);

        return $this->json($data);
    }

    #[Route('/api/customer', name: 'api_customer_get', methods: ['GET'])]
    public function getCustumer(): JsonResponse
    {
        $data = $this->connection->fetchAllAssociative('SELECT * FROM cliente where estado = 1');

        return $this->json($data);
    }

    #[Route('/api/payment-method', name: 'api_payment_method_get', methods: ['GET'])]
    public function getPaymentMethod(): JsonResponse
    {
        $data = $this->connection->fetchAllAssociative('SELECT * FROM payment_type where active = 1');

        return $this->json($data);
    }

    #[Route('/api/warehouse/{id}/product', name: 'api_product_get', methods: ['GET'])]
    public function getProduct($id): JsonResponse
    {
        // $data = $this->connection->fetchAllAssociative("SELECT * FROM producto where almacen_id = ? and estado = 1", [$id]);
        $data = $this->connection->fetchAllAssociative('SELECT * FROM producto where estado = 1');

        return $this->json($data);
    }

    #[Route('/api/operation/{type}', name: 'api_operation_get', methods: ['GET'])]
    public function getOperation(int $type): JsonResponse
    {
        $data = $this->connection->fetchAllAssociative('SELECT * FROM operacion where tipo_id = ? and estado = 1', [$type]);

        return $this->json($data);
    }

    #[Route('/api/supplier', name: 'api_supplier_get', methods: ['GET'])]
    public function getSupplier(): JsonResponse
    {
        $data = $this->connection->fetchAllAssociative('SELECT * FROM proveedor where estado = 1');

        return $this->json($data);
    }

    #[Route('/api/local', name: 'api_local_get', methods: ['GET'])]
    public function getLocal(): JsonResponse
    {
        $data = $this->connection->fetchAllAssociative('SELECT * FROM local where estado = 1');

        return $this->json($data);
    }

    #[Route('/api/voucher-type', name: 'api_voucher-type_get', methods: ['GET'])]
    public function getVoucherType(): JsonResponse
    {
        $data = $this->connection->fetchAllAssociative('SELECT * FROM tipo_comprobante where estado = 1');

        return $this->json($data);
    }

    #[Route('/api/cashbox/detail/{id}', name: 'api_cashbox_detail_get', methods: ['GET'])]
    public function getCashBoxDetail($id): JsonResponse
    {
        $data = $this->connection->fetchAllAssociative('SELECT * FROM caja_detalle where caja_id = ?', [$id]);

        return $this->json($data);
    }

    #[Route('/api/invoice', name: 'api_invoice_get', methods: ['GET'])]
    public function getInvoice(Request $request): JsonResponse
    {
        $filter = $request->query->get('q');

        if (!$filter) {
            return $this->json([]);
        }

        $data = $this->connection->fetchAllAssociative("
            SELECT
                vm.movimiento_id,
                concat( vm.iserie, '-', vm.inumero ) AS comprobante 
            FROM
                vw_movimiento vm
                LEFT JOIN nota nt ON ( nt.movimiento_id = vm.movimiento_id ) 
            WHERE
                vm.inumero > 0 
                AND (nt.envio_sunat IS NULL OR nt.envio_sunat > 0)
                AND concat( vm.iserie, '-', vm.inumero ) like ?
            ORDER BY
                vm.date_created DESC
        ", ["%$filter%"]);

        return $this->json($data);
    }
}
