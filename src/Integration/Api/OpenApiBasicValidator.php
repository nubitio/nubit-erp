<?php

declare(strict_types=1);

namespace App\Integration\Api;

final class OpenApiBasicValidator
{
    /**
     * @param array<array-key, mixed> $operation
     * @return list<string>
     */
    public function validate(string $key, array $operation): array
    {
        $errors = [];
        if (!is_string($operation['operationId'] ?? null)) {
            $errors[] = sprintf('%s is missing operationId.', $key);
        }
        if (!is_array($operation['security'] ?? null) || $operation['security'] === []) {
            $errors[] = sprintf('%s must declare security.', $key);
        }
        $responses = is_array($operation['responses'] ?? null) ? $operation['responses'] : [];
        if (!array_intersect(['200', '201', '202', '204'], array_map('strval', array_keys($responses)))) {
            $errors[] = sprintf('%s must declare a success response.', $key);
        }
        foreach (['401', '403'] as $status) {
            if (!(!array_key_exists($status, $responses) && !array_key_exists((int) $status, $responses))) {
                continue;
            }

            $errors[] = sprintf('%s must declare %s.', $key, $status);
        }

        return $errors;
    }
}
