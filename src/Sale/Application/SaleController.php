<?php

namespace App\Sale\Application;

use App\Base\BaseController;
use App\Core\Infrastructure\Service\PdfExporter;
use App\Core\Infrastructure\Service\XlsExporter;
use App\Sale\Domain\DocType;
use App\Sale\Domain\SaleException;
use App\Sale\Domain\SaleGetOneById;
use App\Sale\Domain\SaleInvoiceUseCase;
use App\Sale\Domain\SaleVoidUseCase;
use App\WebClientPrint\WebClientPrint;
use chillerlan\QRCode\QRCode;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SaleController extends BaseController
{
    #[Route('/sale', name: 'sale_index', methods: ['GET'])]
    public function index(): Response
    {
        $printDetectionScript = WebClientPrint::createWcppDetectionScript($this->generateUrl('wcp', [], UrlGeneratorInterface::ABSOLUTE_URL), session_id());
        $printScript = WebClientPrint::createScript($this->generateUrl('wcp', [], UrlGeneratorInterface::ABSOLUTE_URL), $this->generateUrl('wcp_print', [], UrlGeneratorInterface::ABSOLUTE_URL), session_id());

        return $this->render('sale/index.html.twig', [
            'printDetectionScript' => $printDetectionScript,
            'printScript' => $printScript,
        ]);
    }

    #[Route('/sale-consult', name: 'sale_consult', methods: ['GET'])]
    public function consult(): Response
    {
        return $this->render('sale/consult.html.twig');
    }

    #[Route('/sale/consult', name: 'sale_report', methods: ['GET'])]
    public function report(Request $request, XlsExporter $xlsExporter, PdfExporter $pdfExporter, Connection $connection): StreamedResponse
    {
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
                $data = $connection->executeQuery('CALL sp_reporte_venta_por_producto(:local_id, :date_from, :date_to)', $params)->fetchAllAssociative();
                $reportFile = $xlsExporter->export($data, 'reporte_ventas_por_producto');
                break;
            case 'detailed-sales':
                $data = $connection->executeQuery('CALL sp_reporte_venta_por_producto(:local_id, :date_from, :date_to)', $params)->fetchAllAssociative();
                $reportFile = $xlsExporter->export($data, 'reporte_ventas_detallado');
                break;
            case 'accounting-report':
                $data = $connection->executeQuery('CALL sp_reporte_contable(:date_from, :date_to, :local_id)', $params)->fetchAllAssociative();
                $reportFile = $xlsExporter->export($data, 'reporte_ventas_contable');
                break;
            case 'airport-report':
                $data = $connection->executeQuery('CALL sp_reporte_app(:local_id, :date_from, :date_to)', $params)->fetchAllAssociative();
                $reportFile = $xlsExporter->export($data, 'reporte_ventas_aeropuerto');
                break;
            case 'voided-sales':
                $data = $connection->executeQuery('CALL sp_reporte_anulados(:local_id, :date_from, :date_to)', $params)->fetchAllAssociative();
                $reportFile = $xlsExporter->export($data, 'reporte_ventas_anuladas');
                break;
            case 'credit-notes':
                $data = $connection->executeQuery('CALL sp_reporte_notasCredito(:local_id, :date_from, :date_to)', $params)->fetchAllAssociative();
                $reportFile = $xlsExporter->export($data, 'reporte_notas_de_credito');
                break;
            case 'sale-notes':
                $data = $connection->executeQuery('CALL sp_reporte_notaventa(:local_id, :date_from, :date_to)', $params)->fetchAllAssociative();
                $reportFile = $xlsExporter->export($data, 'reporte_notas_de_pedido');
                break;
            default:
                throw new \Exception('Reporte no encontrado');

        }

        return $reportFile;
    }

    #[Route('/sale/print/{id}', name: 'sale_print', methods: ['GET'])]
    public function print(Request $request, $id, SaleGetOneById $getOneById): Response
    {
        $sale = $getOneById($id);
        $qr = '';
        if ($sale['header']['doc_type'] != DocType::NOTA_VENTA->value) {
            $qr = (new QRCode())->render($sale['header']['doc_qr_data']);
        }
        $logo = base64_encode(file_get_contents('assets/images/logo.png'));
        $content = $this->renderView('sale/report/invoice.html.twig', [
            'logo' => "data:image/png;base64,{$logo}",
            'qr' => $qr,
            'sale' => $sale,
        ]);

        $preview = $request->get('preview');

        if ($preview) {
            return new Response($content);
        }

        $pageHeight = 150;
        if ($sale['header']['doc_type'] != DocType::NOTA_VENTA->value) {
            $pageHeight = 220;
        }

        $items = count($sale['details']);

        $pageHeight += $items * 5;

        $pdfExporter = new PdfExporter();

        return $pdfExporter->export($content, 'invoice', [
            'no-outline',
            'page-width' => '80mm',
            'page-height' => $pageHeight . 'mm',
            'margin-left' => 0,
            'margin-right' => 0,
        ]);
    }

    #[Route('/sale/void/{id}', name: 'sale_void', methods: ['POST'])]
    public function void(Request $request, SaleVoidUseCase $voidUseCase, $id): Response
    {
        $userId = $request->getSession()->get('user_id');
        $voidUseCase($id, json_decode($request->getContent())->reason, $userId);

        return $this->json(['success' => true, 'msg' => 'Venta anulada correctamente']);
    }

    #[Route('/sale/invoice/{id}', name: 'sale_invoice', methods: ['POST'])]
    public function invoice(string $id, SaleInvoiceUseCase $saleInvoiceUseCase): Response
    {
        $saleInvoiceUseCase($id);

        return $this->json(['success' => true, 'msg' => 'Comprobante generado correctamente']);
    }
}
