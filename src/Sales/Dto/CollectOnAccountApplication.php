<?php

declare(strict_types=1);

namespace App\Sales\Dto;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * One sale settled by a "cobro a cuenta" (#381) and by how much.
 */
final readonly class CollectOnAccountApplication
{
    public function __construct(
        #[Groups(['collect_on_account:read'])]
        public string $saleId,
        #[Groups(['collect_on_account:read'])]
        public string $document,
        #[Groups(['collect_on_account:read'])]
        public string $applied,
        #[Groups(['collect_on_account:read'])]
        public string $balanceDueAfter,
    ) {}
}
