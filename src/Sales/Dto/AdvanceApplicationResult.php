<?php

declare(strict_types=1);

namespace App\Sales\Dto;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Result of applying a customer advance to open sales (#382).
 */
final readonly class AdvanceApplicationResult
{
    /**
     * @param list<CollectOnAccountApplication> $applications
     */
    public function __construct(
        #[Groups(['customer_advance:read'])]
        public string $applicationId,
        #[Groups(['customer_advance:read'])]
        public int $customerId,
        #[Groups(['customer_advance:read'])]
        public string $applied,
        #[Groups(['customer_advance:read'])]
        public string $currency,
        #[Groups(['customer_advance:read'])]
        public string $availableAdvance,
        #[Groups(['customer_advance:read'])]
        public array $applications,
    ) {}
}
