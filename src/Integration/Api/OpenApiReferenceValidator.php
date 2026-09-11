<?php

declare(strict_types=1);

namespace App\Integration\Api;

final class OpenApiReferenceValidator
{
    /** @return list<string> */
    public function validate(string $key, array $contract, array $operation): array
    {
        $errors = [];
        $this->walk($key, $contract, $operation, $errors);

        return $errors;
    }

    /** @param list<string> $errors */
    private function walk(string $key, array $contract, mixed $value, array &$errors): void
    {
        if (!is_array($value)) {
            return;
        }
        if (is_string($value['$ref'] ?? null) && !$this->exists($contract, $value['$ref'])) {
            $errors[] = sprintf('%s references missing component %s.', $key, $value['$ref']);
        }
        foreach ($value as $child) {
            $this->walk($key, $contract, $child, $errors);
        }
    }

    private function exists(array $document, string $reference): bool
    {
        if (!str_starts_with($reference, '#/')) {
            return false;
        }
        $value = $document;
        foreach (explode('/', substr($reference, 2)) as $part) {
            $part = str_replace(['~1', '~0'], ['/', '~'], $part);
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return false;
            }
            $value = $value[$part];
        }

        return true;
    }
}
