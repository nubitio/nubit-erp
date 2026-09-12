<?php

declare(strict_types=1);

namespace App\Integration\Api;

final class OpenApiParametersValidator
{
    /**
     * @param array<string, mixed> $operation
     * @return list<string>
     */
    public function validate(string $key, string $path, array $operation): array
    {
        /** @var array{1: list<string>} $matches */
        $matches = [];
        preg_match_all('/\{([^}]+)}/', $path, $matches);
        $parameters = is_array($operation['parameters'] ?? null) ? $operation['parameters'] : [];
        $errors = [];
        foreach ($matches[1] as $name) {
            if ($this->hasRequiredParameter($parameters, $name)) {
                continue;
            }
            $errors[] = sprintf('%s must declare required path parameter %s.', $key, $name);
        }

        return $errors;
    }

    /** @param array<array-key, mixed> $parameters */
    private function hasRequiredParameter(array $parameters, string $name): bool
    {
        foreach ($parameters as $parameter) {
            if (
                is_array($parameter)
                && ($parameter['name'] ?? null) === $name
                && ($parameter['required'] ?? false) === true
            ) {
                return true;
            }
        }

        return false;
    }
}
