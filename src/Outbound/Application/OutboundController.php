<?php

namespace App\Outbound\Application;

use App\Base\BaseController;
use App\Core\Domain\Util\Constants;
use App\Outbound\Domain\Outbound;
use App\Outbound\Domain\OutboundCreateUseCase;
use App\Outbound\Domain\OutboundDeleteUseCase;
use App\Outbound\Domain\OutboundGetAllUseCase;
use App\Outbound\Domain\OutboundUpdateUseCase;
use App\SaleDetail\Domain\SaleDetail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class OutboundController extends BaseController
{
    #[Route('/outbound', name: 'outbound_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('outbound/index.html.twig');
    }

    #[Route('/outbound/get', name: 'outbound_list', methods: ['POST'])]
    public function getAll(Request $request, OutboundGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase([
            [
                'field' => 'numero',
                'op' => 'isnull',
            ],
            [
                'field' => 'tipo_id',
                'op' => 'equal',
                'value' => 2,
            ],
            [
                'field' => 'local_id',
                'op' => 'equal',
                'value' => $request->getSession()->get('branch_id'),
            ],
        ]));
    }

    #[Route('/outbound/add', name: 'outbound_create', methods: ['POST'])]
    public function add(Request $request, OutboundCreateUseCase $createUseCase): Response
    {
        $inboundId = Uuid::v4();
        $request->request->set('movimiento_id', $inboundId);
        /**
         * @var Outbound $outbound
         */
        $outbound = $this->serializer->deserialize(json_encode($request->request->all()), Outbound::class, 'json');
        /**
         * @var array<int, mixed> $detail
         */
        $detail = $request->get('details');
        $detailItems = [];
        foreach ($detail as $item) {
            /**
             * @var SaleDetail $saleDetail
             */
            $saleDetail = $this->serializer->deserialize(json_encode($item), SaleDetail::class, 'json');
            $detailItems[] = $saleDetail;
        }
        $outbound->items = $detailItems;
        $createUseCase($outbound);

        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/outbound/edit/{id}', name: 'outbound_update', methods: ['PUT', 'POST'])]
    public function edit(Request $request, OutboundUpdateUseCase $updateUseCase, string $id): Response
    {
        $request->request->add(['movimiento_id' => $id]);
        /**
         * @var Outbound $outbound
         */
        $outbound = $this->serializer->deserialize(json_encode($request->request->all()), Outbound::class, 'json');
        $updateUseCase($outbound);

        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/outbound/delete/{id}', name: 'outbound_delete', methods: ['DELETE', 'POST'])]
    public function delete(OutboundDeleteUseCase $deleteUseCase, string $id): Response
    {
        $deleteUseCase($id);

        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
