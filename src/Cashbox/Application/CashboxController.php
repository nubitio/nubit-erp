<?php

namespace App\Cashbox\Application;

use App\Base\BaseController;
use App\Branch\Domain\Branch;
use App\Cashbox\Domain\Cashbox;
use App\Cashbox\Domain\CashboxCreateUseCase;
use App\Cashbox\Domain\CashboxGetInfoReceiptUseCase;
use App\Cashbox\Domain\CashboxGetLastUseCase;
use App\Cashbox\Domain\CashboxGetOneUseCase;
use App\Cashbox\Domain\GetOneCashboxUseCase;
use App\Cashbox\Domain\UpdateCashboxUseCase;
use App\CashboxDetail\Domain\CashboxDetail;
use App\Core\Infrastructure\Service\PdfExporter;
use App\User\Domain\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class CashboxController extends BaseController
{
    #[Route('/cashbox', name: 'cashbox_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('cashbox/index.html.twig');
    }

    #[Route('/cashbox-consult', name: 'cashbox_consult', methods: ['GET'])]
    public function consult(): Response
    {
        return $this->render('cashbox/consult.html.twig');
    }

    #[Route('/cashbox/last', name: 'cashbox_last', methods: ['GET'])]
    public function getLast(Request $request, CashboxGetLastUseCase $getLastUseCase): Response
    {
        /** @var string $localId */
        $localId = $request->getSession()->get('branch_id');
        /** @var int $userId */
        $userId = $request->getSession()->get('user_id');

        $cashBox = $getLastUseCase($localId, $userId);

        return $this->json($cashBox);
    }

    #[Route('/cashbox/open', name: 'cashbox_open', methods: ['POST'])]
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

    #[Route('/cashbox/close/{id}', name: 'cashbox_close', methods: ['POST'])]
    public function close(string $id, GetOneCashboxUseCase $getOneCashboxUseCase, UpdateCashboxUseCase $updateCashboxUseCase): Response
    {
        $cashbox = $getOneCashboxUseCase($id);

        $cashbox->cierre = new \DateTime();
        $cashbox->estado = 2;

        $updateCashboxUseCase($cashbox);

        return $this->json($cashbox);
    }

    #[Route('/cashbox/export/{id}', name: 'cashbox_export', methods: ['GET'])]
    public function export(CashboxGetOneUseCase $getOneUseCase, PdfExporter $exporter, string $id): StreamedResponse
    {
        $cashBox = $getOneUseCase($id);

        $content = $this->renderView('cashbox/report/cashbox.html.twig', [
            'title' => 'Reporte de caja',
            'cashbox' => $cashBox,
        ]);

        return $exporter->export($content, 'caja_'.$id);
    }

    #[Route('/cashbox/report/{id}', name: 'cashbox_report', methods: ['GET'])]
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
