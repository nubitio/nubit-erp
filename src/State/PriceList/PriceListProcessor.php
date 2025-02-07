<?php

namespace App\State\PriceList;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Domain\Common\ApiResponse;
use App\Dto\PriceList\PricesDto;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class PriceListProcessor implements ProcessorInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @param PricesDto $data
     *
     * @throws Exception
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ApiResponse {
        $priceListId = $data->priceListId;

        $this->connection->beginTransaction();
        try {
            $insertStmt = $this->connection->prepare(
                'INSERT INTO price_list_detail (product_id, price_list_id, price) VALUES (:productId, :priceListId, :price) ON DUPLICATE KEY UPDATE price = :price'
            );

            foreach ($data->prices as $price) {
                $insertStmt->executeStatement([
                    'productId' => $price->productId,
                    'priceListId' => $priceListId,
                    'price' => $price->price,
                ]);
            }

            $this->connection->commit();

            return new ApiResponse(
                success: true,
                message: 'Precios actualizados correctamente'
            );
        } catch (Exception $e) {
            $this->connection->rollBack();

            return new ApiResponse(
                success: false,
                message: 'Error al actualizar los precios'
            );
        }
    }
}
