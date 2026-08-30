<?php

declare(strict_types=1);

namespace App\Integration\Fiscal\Validation;

/**
 * currency and the three minor-unit amounts.
 */
final class AmountRule implements DocumentRule
{
    public function errors(array $body): array
    {
        $errors = [];

        $currency = FieldReader::str($body, 'currency') ?? 'PEN';
        if (1 !== preg_match('/^[A-Z]{3}$/', $currency)) {
            $errors[] = ['field' => 'currency', 'issue' => 'must be a 3-letter ISO code'];
        }

        foreach (['subtotal_minor', 'tax_minor', 'total_minor'] as $field) {
            $value = FieldReader::int($body, $field);
            if (null === $value || $value < 0) {
                $errors[] = ['field' => $field, 'issue' => 'must be a non-negative integer (minor units)'];
            }
        }

        return $errors;
    }
}
