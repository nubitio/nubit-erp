<?php

namespace App\Core\Infrastructure\Service;

use Doctrine\DBAL\Connection;

class SalesUpdater
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
        $pending = $this->connection->fetchAllAssociative('select * from movimiento where tipo_id = 2 and numero > 0');
        foreach ($pending as $sale) {
            $invoice = $this->connection->fetchAssociative('SELECT * FROM comprobante WHERE movimiento_id = ?', [$sale['movimiento_id']]);
            $receipt = null;
            if ($invoice) {
                $receipt = $invoice['serie'] . '-' . $invoice['numero'];
            }
            $total = null;
            if ($invoice) {
                $total = $invoice['total'];
            } else {
                $saleDetails = $this->connection->fetchAllAssociative('SELECT * FROM movimiento_detalle WHERE movimiento_id = ?', [$sale['movimiento_id']]);
                foreach ($saleDetails as $detail) {
                    $total += $detail['precio_total'];
                }
                $discount = $total * $sale['descuento'] / 100;
                $total -= $discount;
            }

            $this->connection->executeStatement('
                update 
                    movimiento 
                set 
                   invoice = ?,
                   total = ?
                where
                    movimiento_id = ?', [
                $receipt,
                $total,
                $sale['movimiento_id']
            ]);
        }

        return 'Registros actualizados: ' . count($pending);
    }
}
