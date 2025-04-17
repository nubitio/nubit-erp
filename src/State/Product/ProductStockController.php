<?php

namespace App\State\Product;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductStockController extends AbstractController
{
    public function __invoke(
        Connection $connection,
        Request $request,
    ): Response {
        $productId = $request->attributes->get('id') ?? null;
        $warehouseId = $request->attributes->get('warehouseId') ?? null;

        if ($productId === null || $warehouseId === null) {
            return new Response('Invalid parameters', Response::HTTP_BAD_REQUEST);
        }

        $sql = <<<SQL
SELECT
    p.producto_id AS id,
    p.descripcion AS `name`,
    SUM(IF(m.tipo_id = 1, IFNULL(md.cantidad, 0), IFNULL(md.cantidad, 0) * -1)) AS stock
FROM
    producto p
    INNER JOIN movimiento_detalle md ON p.producto_id = md.producto_id
    INNER JOIN movimiento m ON md.movimiento_id = m.movimiento_id
WHERE
    m.estado = 1
    AND p.producto_id = :id
    AND m.almacen_id = :warehouseId
GROUP BY
    p.producto_id, p.descripcion
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
