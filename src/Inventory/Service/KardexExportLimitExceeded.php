<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use RuntimeException;

final class KardexExportLimitExceeded extends RuntimeException
{
    public function __construct(int $limit)
    {
        parent::__construct(sprintf('El exportador de Kardex admite como máximo %d movimientos.', $limit));
    }
}
