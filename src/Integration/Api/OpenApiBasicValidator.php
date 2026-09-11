<?php

declare(strict_types=1);

namespace App\Integration\Api;

final class OpenApiBasicValidator
{
    /** @return list<string> */
    public function validate(string $key, array $operation): array
    {
        $errors = [];
        if (!is_string($operation['operationId'] ?? null)) {
            $errors[] = sprintf('%s is missing operationId.', $key);
        }
        if (($operation['security'] ?? []) === []) {
            $errors[] = sprintf('%s must declare security.', $key);
        }
        if (!array_intersect(['200', '201', '202', '204'], array_keys($operation['responses'] ?? []))) {
            $errors[] = sprintf('%s must declare a success response.', $key);
        }
        foreach (['401', '403'] as $status) {
            if (($operation['responses'][$status] ?? null) === null) {
                $errors[] = sprintf('%s must declare %s.', $key, $status);
            }
        }

        return $errors;
    }
}
