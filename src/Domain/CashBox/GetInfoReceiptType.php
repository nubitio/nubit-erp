<?php

namespace App\Domain\CashBox;

use Doctrine\DBAL\Connection;

class GetInfoReceiptType
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function execute($id): array
    {
        $sql = "
            SELECT
                tc.descripcion AS tipo,
                c.serie,
                min( c.numero ) AS desde,
                max( c.numero ) AS hasta,
                sum( c.total - c.discount_amount ) AS total 
            FROM
                comprobante c
                INNER JOIN tipo_comprobante tc ON tc.codigo = c.tipo_comprobante_id
                INNER JOIN movimiento m ON m.movimiento_id = c.movimiento_id 
            WHERE
                m.caja_id = ? 
            GROUP BY
                tc.codigo
        ";

        return $this->connection->fetchAllAssociative($sql, [$id]);
    }
}