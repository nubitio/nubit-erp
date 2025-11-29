<?php

namespace App\Controller\Api;

use App\Core\Http\ApiResponse;
use App\Repository\InvoiceRepository;
use App\Repository\SaleRepository;
use App\Service\InvoiceCalculator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class MakeInvoice extends AbstractController
{
    /**
     * @throws Exception
     */
    public function __invoke(
        Request $request,
        SaleRepository $saleRepository,
        InvoiceRepository $invoiceRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $saleId = $request->get('id');
        $sale = $saleRepository->find($saleId);

        if ($sale->isHasInvoice()) {
            return ApiResponse::error('La venta ya tiene un comprobante emitido.');
        }

        $localId = $entityManager->getConnection()->fetchOne(
            'select local_id from almacen where almacen_id = ?',
            [$sale->getAlmacen()?->getId()]
        );
        $invoiceCalculator = new InvoiceCalculator(
            floatval($sale->getSubtotal()),
            $sale->getDescuento() ?? 0.0,
            floatval($sale->getIgv()),
            $sale->getRecargoConsumo() ?? 0.0
        );
        $invoiceAmounts = $invoiceCalculator->calculate();
        $entityManager->getConnection()->executeQuery('CALL sp_make_invoice(?,?,?,?,?,?,?,?)', [
            $sale->getId(),
            $localId,
            $invoiceAmounts->getSubtotal(),
            $invoiceAmounts->getTaxedAmount(),
            $invoiceAmounts->getDiscountAmount(),
            $invoiceAmounts->getTax(),
            $invoiceAmounts->getChargesAmount(),
            $invoiceAmounts->getTotal(),
        ]);

        $invoice = $invoiceRepository->findOneBy(
            ['sale' => $saleId]
        );

        $sale->setHasInvoice(true);
        $sale->setInvoice(($invoice?->getSeries() ?? '') . '-' . ($invoice?->getNumber() ?? ''));
        $sale->setTotal($invoice?->getTotal());
        $entityManager->persist($sale);
        $entityManager->flush();

        return ApiResponse::success('Comprobante emitido correctamente.');
    }
}
