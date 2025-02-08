<?php

namespace App\Sale\Application;

use App\Base\BaseController;
use App\Core\Infrastructure\Service\PdfExporter;
use App\Core\Infrastructure\Service\XlsExporter;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class SaleController extends BaseController
{
    #[Route('/sale/consult', name: 'sale_report', methods: ['GET'])]
    public function report(
        Request $request,
        XlsExporter $xlsExporter,
        PdfExporter $pdfExporter,
        Connection $connection,
    ): StreamedResponse {
        $report = $request->get('report');
        $dateFrom = $request->get('date-from');
        $dateTo = $request->get('date-to');
        $localId = $request->getSession()->get('branch_id');
        $params = [
            'local_id' => $localId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
        switch ($report) {
            case 'sale-by-product':
                $data = $connection->executeQuery(
                    'CALL sp_reporte_venta_por_producto(:local_id, :date_from, :date_to)',
                    $params
                )->fetchAllAssociative();
                $reportFile = $xlsExporter->export(
                    $data,
                    'reporte_ventas_por_producto'
                );
                break;
            case 'detailed-sales':
                $data = $connection->executeQuery(
                    'CALL sp_reporte_venta_por_producto(:local_id, :date_from, :date_to)',
                    $params
                )->fetchAllAssociative();
                $reportFile = $xlsExporter->export(
                    $data,
                    'reporte_ventas_detallado'
                );
                break;
            case 'accounting-report':
                $data = $connection->executeQuery(
                    'CALL sp_reporte_contable(:date_from, :date_to, :local_id)',
                    $params
                )->fetchAllAssociative();
                $reportFile = $xlsExporter->export(
                    $data,
                    'reporte_ventas_contable'
                );
                break;
            case 'airport-report':
                $data = $connection->executeQuery(
                    'CALL sp_reporte_app(:local_id, :date_from, :date_to)',
                    $params
                )->fetchAllAssociative();
                $reportFile = $xlsExporter->export(
                    $data,
                    'reporte_ventas_aeropuerto'
                );
                break;
            case 'voided-sales':
                $data = $connection->executeQuery(
                    'CALL sp_reporte_anulados(:local_id, :date_from, :date_to)',
                    $params
                )->fetchAllAssociative();
                $reportFile = $xlsExporter->export(
                    $data,
                    'reporte_ventas_anuladas'
                );
                break;
            case 'credit-notes':
                $data = $connection->executeQuery(
                    'CALL sp_reporte_notasCredito(:date_from, :date_to)',
                    $params
                )->fetchAllAssociative();
                $reportFile = $xlsExporter->export(
                    $data,
                    'reporte_notas_de_credito'
                );
                break;
            case 'sale-notes':
                $data = $connection->executeQuery(
                    'CALL sp_reporte_notaventa(:local_id, :date_from, :date_to)',
                    $params
                )->fetchAllAssociative();
                $reportFile = $xlsExporter->export(
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
