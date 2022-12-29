<?php

namespace App\VoucherSeries\Application;

use App\Base\BaseController;
use App\Util\Constants;
use App\VoucherSeries\Domain\VoucherSeries;
use App\VoucherSeries\Domain\VoucherSeriesCreateUseCase;
use App\VoucherSeries\Domain\VoucherSeriesDeleteUseCase;
use App\VoucherSeries\Domain\VoucherSeriesGetAllUseCase;
use App\VoucherSeries\Domain\VoucherSeriesUpdateUseCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VoucherSeriesController extends BaseController
{
    #[Route('/voucher-series', name: 'voucher-series_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('voucher-series/index.html.twig');
    }

    #[Route('/voucher-series/get', name: 'voucher-series_list', methods: ['POST'])]
    public function getAll(VoucherSeriesGetAllUseCase $getAllUseCase): Response
    {
        return $this->json($getAllUseCase());
    }

    #[Route('/voucher-series/add', name: 'voucher-series_create', methods: ['POST'])]
    public function add(Request $request, VoucherSeriesCreateUseCase $createUseCase): Response
    {
        /**
         * @var VoucherSeries $voucherSeries
         */
        $voucherSeries = $this->serializer->deserialize(json_encode($request->request->all()), VoucherSeries::class, 'json');
        $createUseCase($voucherSeries);

        return $this->json(['success' => true, 'msg' => Constants::CREATED_TEXT]);
    }

    #[Route('/voucher-series/edit/{id}', name: 'voucher-series_update', methods: ['PUT', 'POST'])]
    public function edit(Request $request, VoucherSeriesUpdateUseCase $updateUseCase, $id): Response
    {
        $request->request->add(['id' => $id]);
        /**
         * @var VoucherSeries $voucherSeries
         */
        $voucherSeries = $this->serializer->deserialize(json_encode($request->request->all()), VoucherSeries::class, 'json');
        $updateUseCase($voucherSeries);

        return $this->json(['success' => true, 'msg' => Constants::UPDATED_TEXT]);
    }

    #[Route('/voucher-series/delete/{id}', name: 'voucher-series_delete', methods: ['DELETE', 'POST'])]
    public function delete(VoucherSeriesDeleteUseCase $deleteUseCase, $id): Response
    {
        $deleteUseCase($id);

        return $this->json(['success' => true, 'msg' => Constants::DELETED_TEXT]);
    }
}
