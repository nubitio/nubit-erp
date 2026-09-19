<?php

declare(strict_types=1);

namespace App\Onboarding;

use App\Contacts\Entity\Customer;
use DateTimeImmutable;

/**
 * A just-persisted opening-balance sale, queued to be booked as a CHARGE on
 * the customer's ledger once the batch (or the final flush) has actually
 * assigned that customer's id — see {@see OpeningReceivableImporter}.
 */
final readonly class OpeningReceivableChargeToRecord
{
    public function __construct(
        public Customer $customer,
        public string $saleId,
        public DateTimeImmutable $occurredOn,
        public string $amount,
        public ?string $description,
    ) {}
}
