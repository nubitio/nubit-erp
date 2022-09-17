<?php

namespace App\VoucherType\Application;

use App\Base\BaseController;
use App\VoucherType\Domain\VoucherTypeGetAllUseCase;
use App\VoucherType\Domain\VoucherTypeCreateUseCase;
use App\VoucherType\Domain\VoucherTypeUpdateUseCase;
use App\VoucherType\Domain\VoucherTypeDeleteUseCase;
use App\VoucherType\Domain\VoucherType;
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VoucherTypeController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    #[Route('/vouchertype', name: 'vouchertype_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('vouchertype/index.html.twig');
    }

    #[Route('/vouchertype/get', name: 'vouchertype_list', methods: ['POST'])]
    public function getAll(): Response
    {
        $getAllUseCase = $this->container->get(VoucherTypeGetAllUseCase::class);
        return $this->json($getAllUseCase());
    }

    #[Route('/vouchertype/add', name: 'vouchertype_create', methods: ['POST'])]
    public function add(Request $request): Response
    {
        /**
         * @var VoucherType $vouchertype
         */
        $vouchertype = $this->serializer->deserialize(json_encode($request->request->all()), VoucherType::class, 'json');
        $createUseCase = $this->container->get(VoucherTypeCreateUseCase::class);
        $createUseCase($vouchertype);
        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/vouchertype/edit/{id}', name: 'vouchertype_update', methods: ['POST'])]
    public function edit(Request $request, $id): Response
    {
        $request->request->add(['codigo' => $id]);
        /**
         * @var VoucherType $vouchertype
         */
        $vouchertype = $this->serializer->deserialize(json_encode($request->request->all()), VoucherType::class, 'json');
        $updateUseCase = $this->container->get(VoucherTypeUpdateUseCase::class);
        $updateUseCase($vouchertype);
        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/vouchertype/delete/{id}', name: 'vouchertype_delete', methods: ['POST'])]
    public function delete($id): Response
    {
        $deleteUseCase = $this->container->get(VoucherTypeDeleteUseCase::class);
        $deleteUseCase($id);
        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
