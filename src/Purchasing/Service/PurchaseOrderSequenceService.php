<?php

declare(strict_types=1);

namespace App\Purchasing\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final readonly class PurchaseOrderSequenceService
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @throws Exception
     */
    public function next(): int
    {
        $this->connection->executeStatement('INSERT INTO purchase_order_sequences (id, last_number) VALUES (1, 1)
             ON DUPLICATE KEY UPDATE last_number = last_number + 1');

        /** @var false|int|string $number */
        $number = $this->connection->fetchOne('SELECT last_number FROM purchase_order_sequences WHERE id = 1');

        if ($number === false) {
            throw new \RuntimeException('Unable to allocate the next purchase order number.');
        }

        return (int) $number;
    }
}
