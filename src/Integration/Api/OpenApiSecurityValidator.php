<?php

declare(strict_types=1);

namespace App\Integration\Api;

final class OpenApiSecurityValidator
{
    /**
     * @param array<array-key, mixed> $operation
     * @param array<array-key, mixed> $schemes
     * @return list<string>
     */
    public function validate(string $key, array $operation, array $schemes): array
    {
        return array_merge(
            new OpenApiSecurityRequirementsValidator()->validate($key, $operation, $schemes),
            new OpenApiSecuritySchemeValidator()->validate($key, $operation, $schemes),
        );
    }
}
