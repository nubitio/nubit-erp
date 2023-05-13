<?php

namespace App\Cashbox\Application;

use App\Base\BaseController;
use App\Cashbox\Domain\Cashbox;
use App\Cashbox\Domain\CashboxCreateUseCase;
use App\Cashbox\Domain\CashboxDeleteUseCase;
use App\Cashbox\Domain\CashboxGetAllUseCase;
use App\Cashbox\Domain\CashboxGetInfoReceiptUseCase;
use App\Cashbox\Domain\CashboxGetLastUseCase;
use App\Cashbox\Domain\CashboxGetOneUseCase;
use App\Cashbox\Domain\CashboxUpdateUseCase;
use App\CashboxDetail\Domain\CashboxDetail;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\PdfExporter;
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

    #[Route('/cashbox/get', name: 'cashbox_list', methods: ['POST'])]
    public function getAll(Request $request, CashboxGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase([
            [
                'field' => 'local_id',
                'op'    => 'equal',
                'value' => $request->getSession()->get('branch_id'),
            ],
        ]));
    }

    #[Route('/cashbox/last', name: 'cashbox_last', methods: ['GET', 'POST'])]
    public function getLast(Request $request, CashboxGetLastUseCase $getLastUseCase): Response
    {
        /** @var string $localId */
        $localId = $request->getSession()->get('branch_id');
        /** @var int $userId */
        $userId = $request->getSession()->get('user_id');
        $cashBox = $getLastUseCase($localId, $userId);

        if (!$cashBox) {
            return $this->json([
                'header' => [], 'rows' => [], 'footer' => [],
            ]);
        }

        $data = [];

        $data['header'] = [
            'caja_id'  => $cashBox->caja_id,
            'fecha'    => $cashBox->apertura?->format('Y-m-d'),
            'apertura' => $cashBox->apertura?->format('H:i:s'),
        ];
        $data['rows'] = $cashBox->details;
        $data['footer'] = [
            [
                'descripcion' => 'Ingresos',
                'monto'       => $cashBox->getIncome(),
            ],
            [
                'descripcion' => 'Egresos',
                'monto'       => $cashBox->getOutcome(),
            ],
            [
                'descripcion' => 'Saldo',
                'monto'       => $cashBox->getBalance(),
            ],
        ];

        return $this->json($data);
    }

    #[Route('/cashbox/open', name: 'cashbox_open', methods: ['POST'])]
    public function open(Request $request, CashboxCreateUseCase $createUseCase): Response
    {
        /** @var string $localId */
        $localId = $request->getSession()->get('branch_id');
        /** @var int $userId */
        $userId = $request->getSession()->get('user_id');
        $cashBoxDetail = new CashboxDetail(
            uid: 1,
            tipo_id: 1,
            descripcion: 'Fondo de caja',
            monto: (float)$request->request->get('monto_inicial'),
        );

        $cashBox = new Cashbox(
            local_id: $localId,
            apertura: new \DateTime(),
            usuario_id: $userId,
            estado: 1,
            details: [$cashBoxDetail],
        );

        $createUseCase($cashBox);

        return $this->json(['success' => true]);
    }

    #[Route('/cashbox/add', name: 'cashbox_create', methods: ['POST'])]
    public function add(Request $request, CashboxCreateUseCase $createUseCase): Response
    {
        /**
         * @var Cashbox $cashbox
         */
        $cashbox = $this->serializer->deserialize(json_encode($request->request->all()), Cashbox::class, 'json');
        $createUseCase($cashbox);

        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/cashbox/edit/{id}', name: 'cashbox_update', methods: ['PUT', 'POST'])]
    public function edit(Request $request, CashboxUpdateUseCase $updateUseCase, string $id): Response
    {
        $request->request->add(['caja_id' => $id]);
        /**
         * @var Cashbox $cashbox
         */
        $cashbox = $this->serializer->deserialize(json_encode($request->request->all()), Cashbox::class, 'json');
        $updateUseCase($cashbox);

        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/cashbox/delete/{id}', name: 'cashbox_delete', methods: ['DELETE', 'POST'])]
    public function delete(CashboxDeleteUseCase $deleteUseCase, string $id): Response
    {
        $deleteUseCase($id);

        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }

    #[Route('/cashbox/{id}/details', name: 'cashbox_get', methods: ['GET'])]
    public function getOne(CashboxGetOneUseCase $getOneUseCase, string $id): Response
    {
        $cashBox = $getOneUseCase($id);

        return $this->json($cashBox->details);
    }

    #[Route('/cashbox/export/{id}', name: 'cashbox_export', methods: ['GET'])]
    public function export(Request $request, CashboxGetOneUseCase $getOneUseCase, PdfExporter $exporter, string $id): StreamedResponse
    {
        $cashBox = $getOneUseCase($id);

        $content = $this->renderView('cashbox/report/cashbox.html.twig', [
            'title'   => 'Reporte de caja',
            'cashbox' => $cashBox,
        ]);

        return $exporter->export($content, 'caja_' . $id);
    }

    #[Route('/cashbox/report/{id}', name: 'cashbox_report', methods: ['GET'])]
    public function report(Request $request, CashboxGetOneUseCase $getOneUseCase, CashboxGetInfoReceiptUseCase $getInfoReceiptUseCase, PdfExporter $exporter, string $id): StreamedResponse
    {
        $cashBox = $getOneUseCase($id);
        $infoReceipt = $getInfoReceiptUseCase($id);

        $content = $this->renderView('cashbox/report/cashbox_full.html.twig', [
            'title'   => 'Reporte de caja',
            'cashbox' => $cashBox,
            'receipt' => $infoReceipt,
        ]);

        return $exporter->export($content, 'caja_' . $id);
    }
}
