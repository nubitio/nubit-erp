<?php

declare(strict_types=1);

namespace App\Integration\Fiscal\Validation;

/**
 * The nested `customer` object: name is required, document_type is constrained.
 */
final class CustomerRule implements DocumentRule
{
    public function errors(array $body): array
    {
        $customer = FieldReader::arr($body, 'customer');
        $errors = [];

        $name = FieldReader::str($customer, 'name');
        if (null === $name || '' === trim($name)) {
            $errors[] = ['field' => 'customer.name', 'issue' => 'is required'];
        }

        $docType = FieldReader::str($customer, 'document_type');
        if (null !== $docType && 'RUC' !== $docType && 'DNI' !== $docType) {
            $errors[] = ['field' => 'customer.document_type', 'issue' => 'must be "RUC", "DNI" or null'];
        }

        return $errors;
    }
}
