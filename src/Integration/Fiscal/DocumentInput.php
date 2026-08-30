<?php

declare(strict_types=1);

namespace App\Integration\Fiscal;

use App\Integration\Fiscal\Validation\AmountRule;
use App\Integration\Fiscal\Validation\CustomerRule;
use App\Integration\Fiscal\Validation\DocumentRule;
use App\Integration\Fiscal\Validation\FieldReader;
use App\Integration\Fiscal\Validation\IdentityRule;

/**
 * The validated body of `POST /api/v1/documents`.
 *
 * Amounts stay in integer minor units (céntimos); the factory converts to the
 * decimal strings the domain expects. The series and number are the caller's —
 * this channel never allocates a correlative.
 */
final readonly class DocumentInput
{
    public const string TYPE_INVOICE = 'invoice';
    public const string TYPE_RECEIPT = 'receipt';

    public function __construct(
        public string $externalId,
        public string $documentType,
        public string $series,
        public int $number,
        public string $currency,
        public int $subtotalMinor,
        public int $taxMinor,
        public int $totalMinor,
        public ?string $customerDocType,
        public ?string $customerDocNumber,
        public string $customerName,
        public ?string $customerEmail,
        public ?string $customerAddress,
        public string $lineDescription,
        public ?string $orderReference,
    ) {}

    /**
     * @param array<array-key, mixed> $body
     *
     * @throws InvalidDocumentInput
     */
    public static function fromArray(array $body): self
    {
        /** @var list<DocumentRule> $rules */
        $rules = [new IdentityRule(), new AmountRule(), new CustomerRule()];

        $errors = [];
        foreach ($rules as $rule) {
            $errors = [...$errors, ...$rule->errors($body)];
        }
        if ([] !== $errors) {
            throw new InvalidDocumentInput($errors);
        }

        return self::build($body, FieldReader::arr($body, 'customer'));
    }

    public function isInvoice(): bool
    {
        return self::TYPE_INVOICE === $this->documentType;
    }

    /** SUNAT catálogo 01: 01 = factura, 03 = boleta. */
    public function sunatDocumentType(): string
    {
        return $this->isInvoice() ? '01' : '03';
    }

    /**
     * @param array<array-key, mixed> $body
     * @param array<array-key, mixed> $customer
     */
    private static function build(array $body, array $customer): self
    {
        return new self(
            externalId: strtolower((string) FieldReader::str($body, 'external_id')),
            documentType: (string) FieldReader::str($body, 'document_type'),
            series: (string) FieldReader::str($body, 'series'),
            number: (int) FieldReader::int($body, 'number'),
            currency: FieldReader::str($body, 'currency') ?? 'PEN',
            subtotalMinor: (int) FieldReader::int($body, 'subtotal_minor'),
            taxMinor: (int) FieldReader::int($body, 'tax_minor'),
            totalMinor: (int) FieldReader::int($body, 'total_minor'),
            customerDocType: FieldReader::str($customer, 'document_type'),
            customerDocNumber: FieldReader::str($customer, 'document_number'),
            customerName: (string) FieldReader::str($customer, 'name'),
            customerEmail: FieldReader::str($customer, 'email'),
            customerAddress: FieldReader::str($customer, 'address'),
            lineDescription: FieldReader::str($body, 'line_description') ?? 'Servicio',
            orderReference: FieldReader::str($body, 'order_reference'),
        );
    }
}
