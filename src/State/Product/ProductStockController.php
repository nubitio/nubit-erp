<?php

namespace App\State\Product;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductStockController extends AbstractController
{
    public function __invoke(Connection $connection, Request $request): Response
    {
        $productId = $request->attributes->get('id') ?? null;
        $warehouseId = $request->attributes->get('warehouseId') ?? null;

        if ($productId === null || $warehouseId === null) {
            return new Response('Invalid parameters', Response::HTTP_BAD_REQUEST);
        }

        $sql = <<<SQL
WITH stock_por_producto AS (
    SELECT
        md.producto_id,
        SUM(
            CASE
                WHEN m.tipo_id = 1 THEN COALESCE(md.cantidad, 0)
                ELSE COALESCE(md.cantidad, 0) * -1
            END
        ) AS stock
    FROM movimiento_detalle md
    INNER JOIN movimiento m
        ON md.movimiento_id = m.movimiento_id
        AND m.estado = 1
        AND m.almacen_id = :warehouseId
    GROUP BY md.producto_id
)

SELECT
    p.producto_id AS id,
    p.descripcion AS name,
    COALESCE(sp.stock, 0) AS stock
FROM producto p
LEFT JOIN stock_por_producto sp ON p.producto_id = sp.producto_id
WHERE p.producto_id = :id
SQL;
        $params = [
            'id' => $productId,
            'warehouseId' => $warehouseId,
        ];

        $result = $connection->fetchAssociative($sql, $params);

        if ($result === false) {
            return new Response('Product not found', Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $result['id'],
            'name' => $result['name'],
            'stock' => (int)$result['stock'],
        ]);
    }
}
