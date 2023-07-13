<?php

namespace App\CashboxNew\Application;

use App\Base\BaseController;
use App\Branch\Domain\Branch;
use App\CashboxNew\Domain\Cashbox;
use App\CashboxNew\Domain\CashboxCreateUseCase;
use App\CashboxNew\Domain\CashboxGetInfoReceiptUseCase;
use App\CashboxNew\Domain\CashboxGetLastUseCase;
use App\CashboxNew\Domain\CashboxGetOneUseCase;
use App\CashboxNew\Domain\GetOneCashboxUseCase;
use App\CashboxNew\Domain\UpdateCashboxUseCase;
use App\CashboxNewDetail\Domain\CashboxDetail;
use App\Core\Infrastructure\Service\PdfExporter;
use App\User\Domain\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class CashboxController extends BaseController
{
    #[Route('/cashbox-new', name: 'cashbox_new_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('cashbox/index.html.twig');
    }

    #[Route('/cashbox-new-consult', name: 'cashbox_new_consult', methods: ['GET'])]
    public function consult(): Response
    {
        return $this->render('cashbox/consult.html.twig');
    }

    #[Route('/cashbox-new/last', name: 'cashbox_new_last', methods: ['GET'])]
    public function getLast(Request $request, CashboxGetLastUseCase $getLastUseCase): Response
    {
        /** @var string $localId */
        $localId = $request->getSession()->get('branch_id');
        /** @var int $userId */
        $userId = $request->getSession()->get('user_id');

        $cashBox = $getLastUseCase($localId, $userId);

        return $this->json($cashBox);
    }

    #[Route('/cashbox-new/open', name: 'cashbox_new_open', methods: ['POST'])]
    public function open(Request $request, CashboxCreateUseCase $createUseCase): Response
    {
        /** @var string $localId */
        $localId = $request->getSession()->get('branch_id');
        /** @var int $userId */
        $userId = $request->getSession()->get('user_id');

        /** @var array<string, string> $requestBody */
        $requestBody = json_decode($request->getContent(), true);

        $cashBoxDetail = new CashboxDetail(
            uid: 1,
            tipo_id: 1,
            descripcion: 'FONDO DE CAJA',
            monto: floatval($requestBody['amount']),
        );

        $cashBox = new Cashbox(
            local: new Branch($localId),
            apertura: new \DateTime(),
            usuario: new User($userId),
            estado: 1,
            details: [$cashBoxDetail],
        );

        $createUseCase($cashBox);

        return $this->json($cashBox, Response::HTTP_CREATED);
    }

    #[Route('/cashbox-new/close/{id}', name: 'cashbox_new_close', methods: ['POST'])]
    public function close(string $id, GetOneCashboxUseCase $getOneCashboxUseCase, UpdateCashboxUseCase $updateCashboxUseCase): Response
    {
        $cashbox = $getOneCashboxUseCase($id);

        $cashbox->cierre = new \DateTime();
        $cashbox->estado = 2;

        $updateCashboxUseCase($cashbox);

        return $this->json($cashbox);
    }

    #[Route('/cashbox-new/export/{id}', name: 'cashbox_new_export', methods: ['GET'])]
    public function export(CashboxGetOneUseCase $getOneUseCase, PdfExporter $exporter, string $id): StreamedResponse
    {
        $cashBox = $getOneUseCase($id);

        $content = $this->renderView('cashbox/report/cashbox.html.twig', [
            'title' => 'Reporte de caja',
            'cashbox' => $cashBox,
        ]);

        return $exporter->export($content, 'caja_'.$id);
    }

    #[Route('/cashbox-new/report/{id}', name: 'cashbox_new_report', methods: ['GET'])]
    public function report(CashboxGetOneUseCase $getOneUseCase, CashboxGetInfoReceiptUseCase $getInfoReceiptUseCase, PdfExporter $exporter, string $id): StreamedResponse
    {
        $cashBox = $getOneUseCase($id);
        $infoReceipt = $getInfoReceiptUseCase($id);

        $content = $this->renderView('cashbox/report/cashbox_full.html.twig', [
            'title' => 'Reporte de caja',
            'cashbox' => $cashBox,
            'receipt' => $infoReceipt,
        ]);

        return $exporter->export($content, 'caja_'.$id);
    }
}
