<?php

namespace App\Api\Product;

use App\Base\BaseController;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends BaseController
{
    #[Route('/api/product/{localId}', name: 'api_product_get_all', methods: ['GET'])]
    public function getAllProducts(Connection $connection, string $localId): JsonResponse
    {
        $products = $connection->fetchAllAssociative('SELECT * FROM vw_producto where  estado = 1 and local_id = :localId', ['localId' => $localId]);

        return $this->json($products);
    }

    #[Route('/api/products/{productId}', name: 'api_product_get_one', methods: ['GET'])]
    public function getOneProduct(Connection $connection, string $productId): JsonResponse
    {
        $product = $connection->fetchAssociative('SELECT * FROM vw_producto where  producto_id = :productId', ['productId' => $productId]);

        return $this->json($product);
    }
}