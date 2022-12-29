<?php

namespace App\VoucherType\Application;

use App\Base\BaseController;
use App\Util\Constants;
use App\VoucherType\Domain\VoucherType;
use App\VoucherType\Domain\VoucherTypeCreateUseCase;
use App\VoucherType\Domain\VoucherTypeDeleteUseCase;
use App\VoucherType\Domain\VoucherTypeGetAllUseCase;
use App\VoucherType\Domain\VoucherTypeUpdateUseCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VoucherTypeController extends BaseController
{
    #[Route('/voucher-type', name: 'voucher-type_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('voucher-type/index.html.twig');
    }

    #[Route('/voucher-type/get', name: 'voucher-type_list', methods: ['POST'])]
    public function getAll(VoucherTypeGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase());
    }

    #[Route('/voucher-type/add', name: 'voucher-type_create', methods: ['POST'])]
    public function add(Request $request, VoucherTypeCreateUseCase $createUseCase): Response
    {
        /**
         * @var VoucherType $voucherType
         */
        $voucherType = $this->serializer->deserialize(json_encode($request->request->all()), VoucherType::class, 'json');
        $createUseCase($voucherType);

        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/voucher-type/edit/{id}', name: 'voucher-type_update', methods: ['PUT', 'POST'])]
    public function edit(Request $request, VoucherTypeUpdateUseCase $updateUseCase, $id): Response
    {
        $request->request->add(['codigo' => $id]);
        /**
         * @var VoucherType $voucherType
         */
        $voucherType = $this->serializer->deserialize(json_encode($request->request->all()), VoucherType::class, 'json');
        $updateUseCase($voucherType);

        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/voucher-type/delete/{id}', name: 'voucher-type_delete', methods: ['DELETE', 'POST'])]
    public function delete(VoucherTypeDeleteUseCase $deleteUseCase, $id): Response
    {
        $deleteUseCase($id);

        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
