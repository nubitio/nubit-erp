<?php

declare(strict_types=1);

namespace App\Sales\Dto;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * What a "cobro a cuenta" (#381) did: the amount received and how it was spread
 * across the customer's open sales.
 */
final readonly class CollectOnAccountResult
{
    /**
     * @param list<CollectOnAccountApplication> $applications
     */
    public function __construct(
        #[Groups(['collect_on_account:read'])]
        public string $collectionId,
        #[Groups(['collect_on_account:read'])]
        public int $customerId,
        #[Groups(['collect_on_account:read'])]
        public string $amount,
        #[Groups(['collect_on_account:read'])]
        public string $currency,
        #[Groups(['collect_on_account:read'])]
        public array $applications,
    ) {}
}
