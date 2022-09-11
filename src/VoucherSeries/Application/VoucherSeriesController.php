<?php

namespace App\VoucherSeries\Application;

use App\Base\BaseController;
use App\VoucherSeries\Domain\VoucherSeriesGetAllUseCase;
use App\VoucherSeries\Domain\VoucherSeriesCreateUseCase;
use App\VoucherSeries\Domain\VoucherSeriesUpdateUseCase;
use App\VoucherSeries\Domain\VoucherSeriesDeleteUseCase;
use App\VoucherSeries\Domain\VoucherSeries;
use App\Util\Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VoucherSeriesController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    #[Route('/voucherseries', name: 'voucherseries_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('voucherseries/index.html.twig');
    }

    #[Route('/voucherseries/get', name: 'voucherseries_list', methods: ['POST'])]
    public function getAll(): Response
    {
        $getAllUseCase = $this->container->get(VoucherSeriesGetAllUseCase::class);
        return $this->json($getAllUseCase());
    }

    #[Route('/voucherseries/add', name: 'voucherseries_create', methods: ['POST'])]
    public function add(Request $request): Response
    {
        /**
         * @var VoucherSeries $voucherseries
         */
        $voucherseries = $this->serializer->deserialize(json_encode($request->request->all()), VoucherSeries::class, 'json');
        $createUseCase = $this->container->get(VoucherSeriesCreateUseCase::class);
        $createUseCase($voucherseries);
        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/voucherseries/edit/{id}', name: 'voucherseries_update', methods: ['POST'])]
    public function edit(Request $request, $id): Response
    {
        $request->request->add(['local_id' => $id]);
        /**
         * @var VoucherSeries $voucherseries
         */
        $voucherseries = $this->serializer->deserialize(json_encode($request->request->all()), VoucherSeries::class, 'json');
        $updateUseCase = $this->container->get(VoucherSeriesUpdateUseCase::class);
        $updateUseCase($voucherseries);
        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/voucherseries/delete/{id}', name: 'voucherseries_delete', methods: ['POST'])]
    public function delete($id): Response
    {
        $deleteUseCase = $this->container->get(VoucherSeriesDeleteUseCase::class);
        $deleteUseCase($id);
        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
