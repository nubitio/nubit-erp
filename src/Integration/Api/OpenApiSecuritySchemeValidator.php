<?php

declare(strict_types=1);

namespace App\Integration\Api;

final class OpenApiSecuritySchemeValidator
{
    /**
     * @param array<array-key, mixed> $operation
     * @param array<array-key, mixed> $schemes
     * @return list<string>
     */
    public function validate(string $key, array $operation, array $schemes): array
    {
        $errors = [];
        foreach ($schemes as $name => $scheme) {
            if (is_array($scheme) && ($scheme['type'] ?? null) === 'http' && ($scheme['scheme'] ?? null) === 'bearer') {
                continue;
            }
            $errors[] = sprintf('Security scheme %s must be an HTTP bearer scheme.', (string) $name);
        }

        $apiKey = is_array($schemes['ApiKey'] ?? null) ? $schemes['ApiKey'] : [];
        $supported = is_array($apiKey['x-scopes'] ?? null) ? $apiKey['x-scopes'] : [];
        $requiredScopes = is_array($operation['x-required-scopes'] ?? null) ? $operation['x-required-scopes'] : [];
        foreach ($requiredScopes as $scope) {
            if (in_array($scope, $supported, strict: true)) {
                continue;
            }
            $errors[] = sprintf('%s references unsupported scope %s.', $key, (string) $scope);
        }

        return $errors;
    }
}
