<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Domain\Model;

enum TenantStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Deleted = 'deleted';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::Active;
    }
}