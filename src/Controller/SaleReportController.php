<?php

namespace App\Controller;

use App\Core\Base\BaseController;
use App\Core\Service\XlsExporter;
use App\Core\Symfony\Security\SecurityService;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleReportController extends BaseController
{
    public function __construct(
        private readonly XlsExporter $xlsExporter,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    public function __invoke(Request $request, SecurityService $securityService): StreamedResponse
    {
        $report = $request->query->get('report');
        $dateFrom = $request->query->get('date-from');
        $dateTo = $request->query->get('date-to');
        $localId = $securityService->connectedUser()?->branchId;

        $params = [
            'local_id' => $localId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        switch ($report) {
            case 'sale-by-product':
                $data = $this->connection->executeQuery(
                    'CALL sp_reporte_venta_por_producto(:local_id, :date_from, :date_to)',
                    $params
                )->fetchAllAssociative();
                $reportFile = $this->xlsExporter->export(
                    $data,
                    'reporte_ventas_por_producto'
                );
                break;
            case 'detailed-sales':
                $data = $this->connection->executeQuery(
                    'CALL sp_reporte_venta_por_producto(:local_id, :date_from, :date_to)',
                    $params
                )->fetchAllAssociative();
                $reportFile = $this->xlsExporter->export(
                    $data,
                    'reporte_ventas_detallado'
                );
                break;
            case 'accounting-report':
                $data = $this->connection->executeQuery(
                    'CALL sp_reporte_contable(:date_from, :date_to, :local_id)',
                    $params
                )->fetchAllAssociative();
                $reportFile = $this->xlsExporter->export(
                    $data,
                    'reporte_ventas_contable'
                );
                break;
            case 'airport-report':
                $data = $this->connection->executeQuery(
                    'CALL sp_reporte_app(:local_id, :date_from, :date_to)',
                    $params
                )->fetchAllAssociative();
                $reportFile = $this->xlsExporter->export(
                    $data,
                    'reporte_ventas_aeropuerto'
                );
                break;
            case 'voided-sales':
                $data = $this->connection->executeQuery(
                    'CALL sp_reporte_anulados(:date_from, :date_to)',
                    $params
                )->fetchAllAssociative();
                $reportFile = $this->xlsExporter->export(
                    $data,
                    'reporte_ventas_anuladas'
                );
                break;
            case 'credit-notes':
                $data = $this->connection->executeQuery(
                    'CALL sp_reporte_notasCredito(:date_from, :date_to)',
                    $params
                )->fetchAllAssociative();
                $reportFile = $this->xlsExporter->export(
                    $data,
                    'reporte_notas_de_credito'
                );
                break;
            case 'sale-notes':
                $data = $this->connection->executeQuery(
                    'CALL sp_reporte_notaventa(:local_id, :date_from, :date_to)',
                    $params
                )->fetchAllAssociative();
                $reportFile = $this->xlsExporter->export(
                    $data,
                    'reporte_notas_de_pedido'
                );
                break;
            default:
                throw new \Exception('Reporte no encontrado');
        }

        return $reportFile;
    }
}
