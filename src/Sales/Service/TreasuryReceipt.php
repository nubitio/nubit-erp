<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Catalog\Entity\PaymentMethod;
use App\Treasury\Domain\MovementSource;
use DateTimeImmutable;

/** One payment received from a customer, as Treasury needs to hear about it. */
final readonly class TreasuryReceipt
{
    public function __construct(
        public MovementSource $source,
        public string $sourceId,
        public string $amount,
        public string $currency,
        public PaymentMethod $paymentMethod,
        public DateTimeImmutable $paidAt,
        public ?int $userId,
        /** The sale's branch; null means the user's current branch (an advance has no sale). */
        public ?string $branchId,
        public ?string $customerId,
        public ?string $reference,
        public string $description,
        /** The account the user picked at payment time; null lets the payment method's mapping decide. */
        public ?string $moneyAccountId,
    ) {}
}
