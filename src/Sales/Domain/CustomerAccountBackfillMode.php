<?php

declare(strict_types=1);

namespace App\Sales\Domain;

enum CustomerAccountBackfillMode: string
{
    case DRY_RUN = 'dry-run';
    case APPLY = 'apply';

    public function writes(): bool
    {
        return $this === self::APPLY;
    }
}
