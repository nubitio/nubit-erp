<?php

declare(strict_types=1);

namespace App\Enum;

enum EntityStatus: int
{
    case VOIDED = 0;
    case ACTIVE = 1;

    public function label(): string
    {
        return match ($this) {
            self::VOIDED => 'Anulado',
            self::ACTIVE => 'Activo',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
