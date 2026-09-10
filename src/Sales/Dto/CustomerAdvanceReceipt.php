<?php

declare(strict_types=1);

namespace App\Sales\Dto;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Result of receiving a customer advance (#382).
 */
final readonly class CustomerAdvanceReceipt
{
    public function __construct(
        #[Groups(['customer_advance:read'])]
        public string $advanceId,
        #[Groups(['customer_advance:read'])]
        public int $customerId,
        #[Groups(['customer_advance:read'])]
        public string $amount,
        #[Groups(['customer_advance:read'])]
        public string $currency,
        #[Groups(['customer_advance:read'])]
        public string $availableAdvance,
    ) {}
}
