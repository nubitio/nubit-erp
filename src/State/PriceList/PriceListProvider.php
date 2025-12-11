<?php

declare(strict_types=1);

namespace App\State\PriceList;

use Override;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\PriceList\PriceListDto;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class PriceListProvider implements ProviderInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object | array | null
    {
        $id = $uriVariables['id'];
        $sql = '
            select
                p.producto_id as id,
                p.descripcion as name,
                ifnull(pld.price, p.precio) as price
            from producto p
            left join price_list_detail pld on p.producto_id = pld.product_id
            and pld.price_list_id = :id
            where p.estado = 1
            order by p.descripcion';

        $data = $this->connection->fetchAllAssociative($sql, ['id' => $id]);

        return array_map(
            fn (array $item): PriceListDto => new PriceListDto(
                id: (int)$item['id'],
                name: $item['name'],
                price: (float)$item['price'],
            ),
            $data
        );
    }
}
