<?php

namespace App\Cashbox\Api;

use App\Cashbox\Domain\CashboxGetOneUseCase;
use App\CashboxDetail\Domain\GetCashboxDetailsByCashboxIdUseCase;
use App\Core\Infrastructure\Service\PdfExporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ExportCashbox extends AbstractController
{
    public function __invoke(
        Request $request,
        CashboxGetOneUseCase $getOneUseCase,
        GetCashboxDetailsByCashboxIdUseCase $getDetailsUseCase,
        PdfExporter $exporter,
    ): Response {
        $cashBoxId = $request->attributes->get('caja_id');
        $cashBox = $getOneUseCase->__invoke($cashBoxId);
        $details = $getDetailsUseCase->__invoke($cashBoxId);

        $cashBox->details = $details;

        try {
            $content = $this->renderView('cashbox/report/cashbox.html.twig', [
                'title' => 'Reporte de caja',
                'cashbox' => $cashBox,
            ]);
        } catch (\Exception $e) {
            $content = $e->getMessage();
        }

        return $exporter->export($content, 'caja_'.$cashBoxId);
    }
}
