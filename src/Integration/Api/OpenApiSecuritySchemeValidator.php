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
        /** @var array<string, array<string, mixed>> $typedSchemes */
        $typedSchemes = array_filter($schemes, static fn(mixed $scheme): bool => is_array($scheme));
        foreach ($typedSchemes as $name => $scheme) {
            if (($scheme['type'] ?? null) === 'http' && ($scheme['scheme'] ?? null) === 'bearer') {
                continue;
            }
            $errors[] = sprintf('Security scheme %s must be an HTTP bearer scheme.', $name);
        }

        $apiKey = is_array($schemes['ApiKey'] ?? null) ? $schemes['ApiKey'] : [];
        $supported = is_array($apiKey['x-scopes'] ?? null) ? $apiKey['x-scopes'] : [];
        $requiredScopes = is_array($operation['x-required-scopes'] ?? null) ? $operation['x-required-scopes'] : [];
        /** @var list<string> $typedScopes */
        $typedScopes = array_values(array_filter($requiredScopes, static fn(mixed $scope): bool => is_string($scope)));
        foreach ($typedScopes as $scope) {
            if (in_array($scope, $supported, strict: true)) {
                continue;
            }
            $errors[] = sprintf('%s references unsupported scope %s.', $key, $scope);
        }

        return $errors;
    }
}
