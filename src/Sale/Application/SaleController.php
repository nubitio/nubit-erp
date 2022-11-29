<?php

namespace App\Sale\Application;

use App\Base\BaseController;
use App\Sale\Domain\DocType;
use App\Sale\Domain\Sale;
use App\Sale\Domain\SaleCreateUseCase;
use App\Sale\Domain\SaleDeleteUseCase;
use App\Sale\Domain\SaleGetAllUseCase;
use App\Sale\Domain\SaleGetOneById;
use App\Sale\Domain\SaleInvoiceUseCase;
use App\Sale\Domain\SaleUpdateUseCase;
use App\Sale\Domain\SaleVoidUseCase;
use App\Sale\Infrastructure\SaleUtils;
use App\SaleDetail\Domain\SaleDetail;
use App\Service\PdfExporter;
use App\Service\XlsExporter;
use App\Util\Constants;
use App\WebClientPrint\WebClientPrint;
use chillerlan\QRCode\QRCode;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

class SaleController extends BaseController
{
    #[Route('/sale', name: 'sale_index', methods: ['GET'])]
    public function index(): Response
    {
        $printDetectionScript = WebClientPrint::createWcppDetectionScript($this->generateUrl('wcp', [], UrlGeneratorInterface::ABSOLUTE_URL), session_id());
        $printScript = WebClientPrint::createScript($this->generateUrl('wcp', [], UrlGeneratorInterface::ABSOLUTE_URL), $this->generateUrl('wcp_print', [], UrlGeneratorInterface::ABSOLUTE_URL), session_id());

        return $this->render('sale/index.html.twig', [
            'printDetectionScript' => $printDetectionScript,
            'printScript'          => $printScript,
        ]);
    }

    #[Route('/sale-consult', name: 'sale_consult', methods: ['GET'])]
    public function consult(): Response
    {
        return $this->render('sale/consult.html.twig');
    }

    #[Route('/sale/get', name: 'sale_list', methods: ['POST'])]
    public function getAll(Request $request, SaleGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase([
            [
                'field' => 'tipo_id',
                'op'    => 'equal',
                'value' => 2
            ],
            [
                'field' => 'local_id',
                'op'    => 'equal',
                'value' => $request->getSession()->get('local_id')
            ]
        ]));
    }

    #[Route('/sale/add', name: 'sale_create', methods: ['POST'])]
    public function add(Request $request, SaleCreateUseCase $createUseCase, SaleUtils $saleUtils): Response
    {
        $saleId = Uuid::v4();
        $request->request->set('movimiento_id', $saleId);
        /**
         * @var Sale $sale
         */
        $sale = $this->serializer->deserialize(json_encode($request->request->all()), Sale::class, 'json');
        $sale = $saleUtils->fillSaleValues($sale, DocType::NOTA_VENTA, $request->getSession());
        $detail = $request->get('detail');
        $detailItems = [];
        foreach ($detail as $item) {
            /**
             * @var SaleDetail $saleDetail
             */
            $saleDetail = $this->serializer->deserialize(json_encode($item), SaleDetail::class, 'json');
            $detailItems[] = $saleDetail;
        }
        $sale->items = $detailItems;
        $createUseCase($sale);
        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/sale/edit/{id}', name: 'sale_update', methods: ['PUT', 'POST'])]
    public function edit(Request $request, SaleUpdateUseCase $updateUseCase, $id): Response
    {
        $request->request->add(['movimiento_id' => $id]);
        /**
         * @var Sale $sale
         */
        $sale = $this->serializer->deserialize(json_encode($request->request->all()), Sale::class, 'json');
        $updateUseCase($sale);
        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/sale/delete/{id}', name: 'sale_delete', methods: ['DELETE', 'POST'])]
    public function delete(SaleDeleteUseCase $deleteUseCase, $id): Response
    {
        $deleteUseCase($id);
        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }

    /**
     * @throws Exception
     */
    #[Route('/sale/consult', name: 'sale_report', methods: ['GET'])]
    public function report(Request $request, XlsExporter $xlsExporter, PdfExporter $pdfExporter, Connection $connection): StreamedResponse
    {
        $report = $request->get('report');
        $dateFrom = $request->get('date-from');
        $dateTo = $request->get('date-to');
        $localId = $request->getSession()->get('local_id');
        $params = [
            'local_id'  => $localId,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo
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
                $data = $connection->executeQuery('CALL sp_reporte_contable(:local_id, :date_from, :date_to)', $params)->fetchAllAssociative();
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
        }
        return $reportFile;
    }

    #[Route('/sale/print/{id}', name: 'sale_print', methods: ['GET'])]
    public function print(Request $request, $id, SaleGetOneById $getOneById): Response
    {
        $sale = $getOneById($id);
        $qr = '';
        if ($sale['header']['doc_type'] != DocType::NOTA_VENTA->value) {
            $qr = (new QRCode)->render($sale['header']['doc_qr_data']);
        }
        $logo = base64_encode(file_get_contents('assets/images/logo.png'));
        $content = $this->render('sale/report/invoice.html.twig', [
            'logo' => "data:image/png;base64,{$logo}",
            'qr'   => $qr,
            'sale' => $sale
        ]);

        $preview = $request->get('preview');

        if ($preview) {
            return $content;
        }

        $pageHeight = 150;
        if ($sale['header']['doc_type'] != DocType::NOTA_VENTA->value) {
            $pageHeight = 220;
        }

        $items = count($sale['details']);

        $pageHeight += $items * 5;

        $pdfExporter = new PdfExporter();
        return $pdfExporter->export($content->getContent(), 'invoice', [
            'no-outline',
            'page-width'   => '80mm',
            'page-height'  => $pageHeight . 'mm',
            'margin-left'  => 0,
            'margin-right' => 0,
        ]);
    }

    #[Route('/sale/void/{id}', name: 'sale_void', methods: ['POST'])]
    public function void(Request $request, SaleVoidUseCase $voidUseCase, $id): Response
    {
        $userId = $request->getSession()->get('usuario_id');
        $voidUseCase($id, json_decode($request->getContent())->reason, $userId);
        return $this->json(['success' => true, 'msg' => 'Venta anulada correctamente']);
    }

    #[Route('/sale/invoice/{id}', name: 'sale_invoice', methods: ['GET'])]
    public function invoice(Request $request, SaleInvoiceUseCase $saleInvoiceUseCase, $id): Response
    {
        $userId = $request->getSession()->get('usuario_id');
        $saleInvoiceUseCase($id, $userId);
        return $this->json(['success' => true, 'msg' => 'Comprobante generado correctamente']);
    }
}
