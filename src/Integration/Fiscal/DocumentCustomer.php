<?php

declare(strict_types=1);

namespace App\Integration\Fiscal;

/** The customer block of an integration document, as the caller sent it. */
final readonly class DocumentCustomer
{
    public function __construct(
        public ?string $docType,
        public ?string $docNumber,
        public string $name,
        public ?string $email,
        public ?string $address,
    ) {}
}
