<?php

declare(strict_types=1);

namespace App\Integration\Api;

final class OpenApiSecurityValidator
{
    /** @return list<string> */
    public function validate(string $key, array $operation, array $schemes): array
    {
        return array_merge(
            new OpenApiSecurityRequirementsValidator()->validate($key, $operation, $schemes),
            new OpenApiSecuritySchemeValidator()->validate($key, $operation, $schemes),
        );
    }
}
