<?php

namespace App\CashboxDetail\Application;

use App\Base\BaseController;
use App\CashboxDetail\Domain\CashboxDetail;
use App\CashboxDetail\Domain\CashboxDetailCreateUseCase;
use App\CashboxDetail\Domain\CashboxDetailDeleteUseCase;
use App\CashboxDetail\Domain\CashboxDetailGetAllUseCase;
use App\CashboxDetail\Domain\CashboxDetailUpdateUseCase;
use App\Core\Domain\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CashboxDetailController extends BaseController
{
    #[Route('/cashbox-detail/get', name: 'cashbox-detail_list', methods: ['POST'])]
    public function getAll(CashboxDetailGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase());
    }

    #[Route('/cashbox-detail/add', name: 'cashbox-detail_create', methods: ['POST'])]
    public function add(Request $request, CashboxDetailCreateUseCase $createUseCase): Response
    {
        /**
         * @var CashboxDetail $cashboxDetail
         */
        $cashboxDetail = $this->serializer->deserialize(json_encode($request->request->all()), CashboxDetail::class, 'json');
        $createUseCase($cashboxDetail);

        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/cashbox-detail/edit/{id}', name: 'cashbox-detail_update', methods: ['PUT', 'POST'])]
    public function edit(Request $request, CashboxDetailUpdateUseCase $updateUseCase, int $id): Response
    {
        $request->request->add(['id' => $id]);
        /**
         * @var CashboxDetail $cashboxDetail
         */
        $cashboxDetail = $this->serializer->deserialize(json_encode($request->request->all()), CashboxDetail::class, 'json');
        $updateUseCase($cashboxDetail);

        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/cashbox-detail/delete/{id}', name: 'cashbox-detail_delete', methods: ['DELETE', 'POST'])]
    public function delete(CashboxDetailDeleteUseCase $deleteUseCase, int $id): Response
    {
        $deleteUseCase($id);

        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
