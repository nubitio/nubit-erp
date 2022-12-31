<?php

namespace App\Inbound\Application;

use App\Base\BaseController;
use App\Inbound\Domain\Inbound;
use App\Inbound\Domain\InboundCreateUseCase;
use App\Inbound\Domain\InboundDeleteUseCase;
use App\Inbound\Domain\InboundGetAllUseCase;
use App\Inbound\Domain\InboundUpdateUseCase;
use App\SaleDetail\Domain\SaleDetail;
use App\Core\Domain\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class InboundController extends BaseController
{
    #[Route('/inbound', name: 'inbound_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('inbound/index.html.twig');
    }

    #[Route('/inbound/get', name: 'inbound_list', methods: ['POST'])]
    public function getAll(Request $request, InboundGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase([
            [
                'field' => 'tipo_id',
                'op' => 'equal',
                'value' => 1,
            ],
            [
                'field' => 'local_id',
                'op' => 'equal',
                'value' => $request->getSession()->get('local_id'),
            ],
        ]));
    }

    #[Route('/inbound/add', name: 'inbound_create', methods: ['POST'])]
    public function add(Request $request, InboundCreateUseCase $createUseCase): Response
    {
        $inboundId = Uuid::v4();
        $request->request->set('movimiento_id', $inboundId);
        /**
         * @var Inbound $inbound
         */
        $inbound = $this->serializer->deserialize(json_encode($request->request->all()), Inbound::class, 'json');
        $detail = $request->get('detail');
        $detailItems = [];
        foreach ($detail as $item) {
            /**
             * @var SaleDetail $saleDetail
             */
            $saleDetail = $this->serializer->deserialize(json_encode($item), SaleDetail::class, 'json');
            $detailItems[] = $saleDetail;
        }
        $inbound->items = $detailItems;
        $createUseCase($inbound);

        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/inbound/edit/{id}', name: 'inbound_update', methods: ['PUT', 'POST'])]
    public function edit(Request $request, InboundUpdateUseCase $updateUseCase, $id): Response
    {
        $request->request->add(['movimiento_id' => $id]);
        /**
         * @var Inbound $inbound
         */
        $inbound = $this->serializer->deserialize(json_encode($request->request->all()), Inbound::class, 'json');
        $updateUseCase($inbound);

        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/inbound/delete/{id}', name: 'inbound_delete', methods: ['DELETE', 'POST'])]
    public function delete(InboundDeleteUseCase $deleteUseCase, $id): Response
    {
        $deleteUseCase($id);

        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
