<?php

namespace App\Controller\Api;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use App\Core\Http\ApiResponse;
use App\Repository\InvoiceRepository;
use App\Repository\SaleRepository;
use App\Service\InvoiceCalculator;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

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
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $voucherType = strlen($sale->getCliente()?->getRuc() ?? '') === 11 ? '01' : '03';

            $existingInvoiceId = $connection->fetchOne(
                'SELECT comprobante_id FROM comprobante WHERE movimiento_id = :saleId FOR UPDATE',
                ['saleId' => $sale->getId()]
            );
            if ($existingInvoiceId) {
                $connection->commit();

                return ApiResponse::error('La venta ya tiene un comprobante emitido.');
            }

            $series = $connection->fetchOne(
                'SELECT serie
                        FROM local_comprobante
                        WHERE local_id = :localId
                          AND tipo_comprobante_id = :voucherType
                        LIMIT 1',
                [
                    'localId' => $localId,
                    'voucherType' => $voucherType,
                ]
            );

            if (!$series) {
                $connection->rollBack();

                return ApiResponse::error('No hay serie configurada para el tipo de comprobante.');
            }

            $nextNumber = $connection->fetchOne(
                'SELECT IFNULL(MAX(CAST(numero AS SIGNED)), 0) + 1
                 FROM comprobante
                 WHERE serie = :series
                 FOR UPDATE',
                ['series' => $series]
            );

            $connection->insert('comprobante', [
                'comprobante_id' => Uuid::v4()->toRfc4122(),
                'raw_document_id' => null,
                'movimiento_id' => $sale->getId(),
                'fecha' => new DateTimeImmutable('now', new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                'tipo_comprobante_id' => $voucherType,
                'serie' => $series,
                'numero' => $nextNumber,
                'cliente_id' => $sale->getCliente()?->getRuc(),
                'inafecto' => 0,
                'subtotal' => $invoiceAmounts->getSubtotal(),
                'gravada' => $invoiceAmounts->getTaxedAmount(),
                'igv' => $invoiceAmounts->getTax(),
                'rc' => $invoiceAmounts->getChargesAmount(),
                'total' => $invoiceAmounts->getTotal(),
                'discount_percent' => $sale->getDescuento() ?? 0.0,
                'discount_amount' => $invoiceAmounts->getDiscountAmount(),
                'voided' => $sale->getEstado(),
                'created_at' => $sale->getDateCreated()?->format('Y-m-d H:i:s'),
            ]);

            $connection->commit();
        } catch (Throwable $throwable) {
            $connection->rollBack();
            throw $throwable;
        }

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
