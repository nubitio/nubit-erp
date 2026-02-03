<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Sale;
use App\Repository\InvoiceRepository;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class InvoiceCreationService
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{success: bool, message: string}
     *
     * @throws Exception
     */
    public function createForSale(Sale $sale): array
    {
        if ($sale->isHasInvoice() && $sale->getInvoice() !== null) {
            return [
                'success' => false,
                'message' => 'La venta ya tiene un comprobante emitido.',
            ];
        }

        $saleDate = $sale->getFecha();
        if (!$saleDate) {
            return [
                'success' => false,
                'message' => 'La venta no tiene fecha válida.',
            ];
        }

        $saleDateUtc = (clone $saleDate)->setTimezone(new DateTimeZone('UTC'));
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if ($saleDateUtc < $nowUtc->modify('-3 days')) {
            return [
                'success' => false,
                'message' => 'La fecha de la venta excede el límite permitido para emisión (3 días).',
            ];
        }

        $connection = $this->entityManager->getConnection();
        $localId = $connection->fetchOne(
            'select local_id from almacen where almacen_id = ?',
            [$sale->getAlmacen()?->getId()]
        );

        $invoiceCalculator = InvoiceCalculator::fromSale($sale);
        $invoiceAmounts = $invoiceCalculator->calculate();

        $connection->beginTransaction();
        try {
            $voucherType = strlen($sale->getCliente()?->getRuc() ?? '') === 11 ? '01' : '03';

            $existingInvoiceId = $connection->fetchOne(
                'SELECT comprobante_id FROM comprobante WHERE movimiento_id = :saleId FOR UPDATE',
                ['saleId' => $sale->getId()]
            );
            if ($existingInvoiceId) {
                $connection->commit();

                return [
                    'success' => false,
                    'message' => 'La venta ya tiene un comprobante emitido.',
                ];
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

                return [
                    'success' => false,
                    'message' => 'No hay serie configurada para el tipo de comprobante.',
                ];
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
                'fecha' => $saleDateUtc->format('Y-m-d H:i:s'),
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
                'tipo_operacion' => $sale->getTipoOperacion(),
                'detraccion_codigo' => $sale->getDetraccionCodigo(),
                'detraccion_porcentaje' => $sale->getDetraccionPorcentaje(),
                'detraccion_monto' => $sale->getDetraccionMonto(),
                'detraccion_medio_pago' => $sale->getDetraccionMedioPago(),
                'detraccion_valor_referencial' => $sale->getDetraccionValorReferencial(),
                'discount_percent' => $sale->getDescuento() ?? 0.0,
                'discount_amount' => $invoiceAmounts->getDiscountAmount(),
                'voided' => $sale->getEstado(),
                'created_at' => $sale->getDateCreated()?->format('Y-m-d H:i:s'),
                'branch_id' => $sale->getBranch()?->getId(),
            ]);

            $connection->commit();
        } catch (Throwable $throwable) {
            $connection->rollBack();
            throw $throwable;
        }

        $invoice = $this->invoiceRepository->findOneBy(
            ['sale' => $sale->getId()]
        );

        $sale->setHasInvoice(true);
        $sale->setInvoice(($invoice?->getSeries() ?? '') . '-' . ($invoice?->getNumber() ?? ''));
        $sale->setTotal($invoice?->getTotal());

        $this->entityManager->persist($sale);
        $this->entityManager->flush();

        return [
            'success' => true,
            'message' => 'Comprobante emitido correctamente.',
        ];
    }
}
