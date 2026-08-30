<?php

declare(strict_types=1);

namespace App\Integration\Fiscal\Validation;

use App\Integration\Fiscal\DocumentInput;

/**
 * external_id, document_type, series and number.
 */
final class IdentityRule implements DocumentRule
{
    public function errors(array $body): array
    {
        $errors = [];

        $externalId = FieldReader::str($body, 'external_id');
        if (null === $externalId || 1 !== preg_match('/^[0-9a-fA-F-]{16,64}$/', $externalId)) {
            $errors[] = ['field' => 'external_id', 'issue' => 'must be a UUID-like identifier'];
        }

        $type = FieldReader::str($body, 'document_type');
        if (DocumentInput::TYPE_INVOICE !== $type && DocumentInput::TYPE_RECEIPT !== $type) {
            $errors[] = ['field' => 'document_type', 'issue' => 'must be "invoice" or "receipt"'];
        }

        $series = FieldReader::str($body, 'series');
        if (null === $series || 1 !== preg_match('/^[A-Z][A-Z0-9]{3}$/', $series)) {
            $errors[] = ['field' => 'series', 'issue' => 'must be a 4-character SUNAT series, e.g. F001'];
        }

        $number = FieldReader::int($body, 'number');
        if (null === $number || $number < 1) {
            $errors[] = ['field' => 'number', 'issue' => 'must be a positive integer'];
        }

        return $errors;
    }
}
