<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Infrastructure\Persistence;

enum TenantSourceMode: string
{
    case Database = 'db';
    case Yaml = 'yaml';

    public static function fromEnv(string $value): self
    {
        $normalized = strtolower(trim($value));

        return $normalized === 'yaml' ? self::Yaml : self::Database;
    }
}