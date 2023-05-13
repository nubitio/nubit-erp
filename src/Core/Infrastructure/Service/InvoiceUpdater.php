<?php

namespace App\Core\Infrastructure\Service;

use App\Core\Domain\Invoice\InvoiceCalculator;
use Doctrine\DBAL\Connection;

class InvoiceUpdater
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(): string
    {
        $pending = $this->connection->fetchAllAssociative('select * from comprobante where envio_sunat is null');
        foreach ($pending as $invoice) {
            /** @var false|array<string, string|int|float|null> $sale */
            $sale = $this->connection->fetchAssociative('SELECT * FROM vw_movimiento WHERE movimiento_id = ?', [$invoice['movimiento_id']]);

            if (false === $sale) {
                continue;
            }

            $invoiceCalculator = new InvoiceCalculator(
                floatval($sale['total']),
                floatval($sale['descuento'] ?? 0.0),
                floatval($sale['igv']),
                floatval($sale['recargo_consumo'] ?? 0.0)
            );

            $invoiceAmounts = $invoiceCalculator->calculate();
            $this->connection->executeStatement('
                update 
                    comprobante 
                set 
                    subtotal = ?, 
                    gravada = ?, 
                    igv = ?, 
                    total = ?, 
                    discount_percent = ?, 
                    discount_amount = ?
                where
                    comprobante_id = ?', [
                $invoiceAmounts->getSubtotal(),
                $invoiceAmounts->getTaxedAmount(),
                $invoiceAmounts->getTax(),
                $invoiceAmounts->getTotal(),
                $sale['descuento'],
                $invoiceAmounts->getDiscountAmount(),
                $invoice['comprobante_id'],
            ]);
        }

        return 'Registros actualizados: '.count($pending);
    }
}
