<?php

namespace App\Purchase\Application;

use App\Base\BaseController;
use App\Purchase\Domain\Purchase;
use App\Purchase\Domain\PurchaseCreateUseCase;
use App\Purchase\Domain\PurchaseDeleteUseCase;
use App\Purchase\Domain\PurchaseGetAllUseCase;
use App\Purchase\Domain\PurchaseUpdateUseCase;
use App\SaleDetail\Domain\SaleDetail;
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class PurchaseController extends BaseController
{
    #[Route('/purchase', name: 'purchase_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('purchase/index.html.twig');
    }

    #[Route('/purchase/get', name: 'purchase_list', methods: ['POST'])]
    public function getAll(Request $request, PurchaseGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase([
            [
                'field' => 'tipo_id',
                'op'    => 'equal',
                'value' => 1
            ],
            [
                'field' => 'local_id',
                'op'    => 'equal',
                'value' => $request->getSession()->get('local_id')
            ]
        ]));
    }

    #[Route('/purchase/add', name: 'purchase_create', methods: ['POST'])]
    public function add(Request $request, PurchaseCreateUseCase $createUseCase): Response
    {
        $purchaseId = Uuid::v4();
        $request->request->set('movimiento_id', $purchaseId);
        /**
         * @var Purchase $purchase
         */
        $purchase = $this->serializer->deserialize(json_encode($request->request->all()), Purchase::class, 'json');
        $detail = $request->get('detail');
        $detailItems = [];
        foreach ($detail as $item) {
            /**
             * @var SaleDetail $saleDetail
             */
            $saleDetail = $this->serializer->deserialize(json_encode($item), SaleDetail::class, 'json');
            $detailItems[] = $saleDetail;
        }
        $purchase->items = $detailItems;
        $createUseCase($purchase);
        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/purchase/edit/{id}', name: 'purchase_update', methods: ['PUT', 'POST'])]
    public function edit(Request $request, PurchaseUpdateUseCase $updateUseCase, $id): Response
    {
        $request->request->add(['movimiento_id' => $id]);
        /**
         * @var Purchase $purchase
         */
        $purchase = $this->serializer->deserialize(json_encode($request->request->all()), Purchase::class, 'json');
        $updateUseCase($purchase);
        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/purchase/delete/{id}', name: 'purchase_delete', methods: ['DELETE', 'POST'])]
    public function delete(PurchaseDeleteUseCase $deleteUseCase, $id): Response
    {
        $deleteUseCase($id);
        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
