<?php

namespace App\Infrastructure\Service;

use App\Domain\Invoice\InvoiceCalculator;
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
            $sale = $this->connection->fetchAssociative('SELECT * FROM vw_movimiento WHERE movimiento_id = ?', [$invoice['movimiento_id']]);
            $invoiceCalculator = new InvoiceCalculator($sale['total'], $sale['descuento'] ?? 0.0, $sale['igv'], $sale['recargo_consumo'] ?? 0.0);
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
