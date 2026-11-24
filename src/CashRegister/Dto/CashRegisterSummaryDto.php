<?php

declare(strict_types=1);

namespace App\CashRegister\Dto;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * The branch's cash figures for the "Cajas y turnos" header: what was sold today and how the closings of the last
 * week turned out. Amounts are decimal strings.
 */
final readonly class CashRegisterSummaryDto
{
    public function __construct(
        /** Sold today in the branch's shifts (voided sales excluded), whatever the payment method. */
        #[Groups(['cash_register_summary:read'])]
        public string $salesToday,
        /** Registers that had a shift open at some point today, or still have one open. */
        #[Groups(['cash_register_summary:read'])]
        public int $registersWithShiftToday,
        /** Net of the closing differences of the last 7 days: negative is missing cash. */
        #[Groups(['cash_register_summary:read'])]
        public string $closureDifference7Days,
        /** Closed shifts of the last 7 days that closed with missing cash. */
        #[Groups(['cash_register_summary:read'])]
        public int $shortageShifts7Days,
        /** Every shift the branch has had, for the paging of the history. */
        #[Groups(['cash_register_summary:read'])]
        public int $shiftsTotal = 0,
    ) {}
}
