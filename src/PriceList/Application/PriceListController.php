<?php

namespace App\PriceList\Application;

use App\Base\BaseController;
use App\PriceList\Domain\GetPricesByList;
use App\PriceList\Domain\SavePriceList;
use App\PriceListDetail\Domain\PriceListDetail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PriceListController extends BaseController
{
    #[Route('/price-list', name: 'price-list_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('price-list/index.html.twig');
    }

    #[Route('/price-list/{id}/get', name: 'price-list_get', methods: ['GET'])]
    public function getPricesByList(int $id, GetPricesByList $getPricesByList): Response
    {
        return $this->json($getPricesByList($id));
    }

    #[Route('/price-list/update', name: 'price-list_update', methods: ['POST'])]
    public function updatePrices(Request $request, SavePriceList $savePriceList): Response
    {
        /** @var PriceListDetail[] $prices */
        $prices = $this->serializer->deserialize($request->getContent(), PriceListDetail::class.'[]', 'json');
        $savePriceList($prices);

        return $this->json(['success' => true, 'msg' => 'Precios actualizados']);
    }
}
