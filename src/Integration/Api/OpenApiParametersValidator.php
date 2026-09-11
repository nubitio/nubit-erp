<?php

declare(strict_types=1);

namespace App\Integration\Api;

final class OpenApiParametersValidator
{
    /** @return list<string> */
    public function validate(string $key, string $path, array $operation): array
    {
        preg_match_all('/\{([^}]+)}/', $path, $matches);
        $parameters = is_array($operation['parameters'] ?? null) ? $operation['parameters'] : [];
        $errors = [];
        foreach ($matches[1] ?? [] as $name) {
            if ($this->hasRequiredParameter($parameters, $name)) {
                continue;
            }
            $errors[] = sprintf('%s must declare required path parameter %s.', $key, $name);
        }

        return $errors;
    }

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
