<?php

declare(strict_types=1);

namespace App\CashRegister\Dto;

use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Groups;

/** One row of the "recent shifts" table: a shift of the branch with what it sold. */
final readonly class CashSessionRecentDto
{
    public function __construct(
        #[Groups(['cash_session_recent:read'])]
        public int $id,
        #[Groups(['cash_session_recent:read'])]
        public int $cashRegisterId,
        #[Groups(['cash_session_recent:read'])]
        public string $cashRegisterName,
        #[Groups(['cash_session_recent:read'])]
        public ?string $cashier,
        #[Groups(['cash_session_recent:read'])]
        public DateTimeImmutable $openedAt,
        #[Groups(['cash_session_recent:read'])]
        public ?DateTimeImmutable $closedAt,
        /** `open` or `closed`. */
        #[Groups(['cash_session_recent:read'])]
        public string $status,
        #[Groups(['cash_session_recent:read'])]
        public string $openingAmount,
        /** What the shift sold (voided sales excluded), credit included. */
        #[Groups(['cash_session_recent:read'])]
        public string $sales,
        /** Counted minus expected at the closing; null while the shift is open or if it was never counted. */
        #[Groups(['cash_session_recent:read'])]
        public ?string $difference,
    ) {}
}
