<?php

namespace App\Cashbox\Application;

use App\Base\BaseController;
use App\Cashbox\Domain\CashboxGetAllUseCase;
use App\Cashbox\Domain\CashboxCreateUseCase;
use App\Cashbox\Domain\CashboxUpdateUseCase;
use App\Cashbox\Domain\CashboxDeleteUseCase;
use App\Cashbox\Domain\Cashbox;
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CashboxController extends BaseController
{
    #[Route('/cashbox', name: 'cashbox_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('cashbox/index.html.twig');
    }

    #[Route('/cashbox/get', name: 'cashbox_list', methods: ['POST'])]
    public function getAll(CashboxGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase());
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

    #[Route('/cashbox/edit/{id}', name: 'cashbox_update', methods: ['POST'])]
    public function edit(Request $request, CashboxUpdateUseCase $updateUseCase, $id): Response
    {
        $request->request->add(['caja_id' => $id]);
        /**
         * @var Cashbox $cashbox
         */
        $cashbox = $this->serializer->deserialize(json_encode($request->request->all()), Cashbox::class, 'json');
        $updateUseCase($cashbox);
        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/cashbox/delete/{id}', name: 'cashbox_delete', methods: ['POST'])]
    public function delete(CashboxDeleteUseCase $deleteUseCase, $id): Response
    {
        $deleteUseCase($id);
        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
